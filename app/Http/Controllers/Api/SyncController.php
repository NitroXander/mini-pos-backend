<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\SaleResource;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class SyncController extends Controller
{
    public function __construct(private readonly SaleSyncService $sales) {}

    public function catalog(Request $request): AnonymousResourceCollection
    {
        $this->authorizeSellOrManage($request);

        $query = Product::query()->orderBy('name');

        if ($request->filled('updated_after')) {
            $query->where('updated_at', '>', Carbon::parse($request->string('updated_after')->toString()));
        }

        return ProductResource::collection($query->get());
    }

    public function sales(Request $request): JsonResponse
    {
        abort_unless($request->user()?->abilities() && in_array('sales.create', $request->user()->abilities(), true), 403);

        $payload = $request->validate([
            'sales' => ['required', 'array', 'min:1', 'max:50'],
            'sales.*.client_uuid' => ['required', 'uuid'],
            'sales.*.sold_at' => ['required', 'date'],
            'sales.*.cashier_id' => ['nullable', 'integer'],
            'sales.*.discount_minor' => ['nullable', 'integer', 'min:0'],
            'sales.*.payment_method' => ['nullable', 'in:cash'],
            'sales.*.lines' => ['required', 'array', 'min:1'],
            'sales.*.lines.*.product_id' => ['nullable', 'integer'],
            'sales.*.lines.*.sku' => ['required', 'string', 'max:64'],
            'sales.*.lines.*.name' => ['required', 'string', 'max:255'],
            'sales.*.lines.*.unit' => ['nullable', 'string', 'max:32'],
            'sales.*.lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'sales.*.lines.*.unit_price_minor' => ['required', 'integer', 'min:0'],
        ]);

        $results = [];

        foreach ($payload['sales'] as $sale) {
            $subtotal = 0;

            foreach ($sale['lines'] as $line) {
                $qty = number_format((float) $line['qty'], 3, '.', '');
                $subtotal += (int) round((float) bcmul($qty, (string) $line['unit_price_minor'], 4));
            }

            $discount = (int) ($sale['discount_minor'] ?? 0);

            if ($discount > $subtotal) {
                $results[] = [
                    'client_uuid' => $sale['client_uuid'],
                    'accepted' => false,
                    'duplicate' => false,
                    'number' => null,
                    'stock_warning' => false,
                    'message' => 'Discount cannot exceed the subtotal.',
                ];

                continue;
            }

            $results[] = $this->sales->accept($request->user(), $sale);
        }

        return response()->json([
            'results' => $results,
        ]);
    }

    public function status(Request $request): AnonymousResourceCollection
    {
        abort_unless(in_array('reports.eod', $request->user()?->abilities() ?? [], true), 403);

        $warnings = Sale::query()
            ->with('lines')
            ->where('stock_warning', true)
            ->latest('sold_at')
            ->limit(20)
            ->get();

        return SaleResource::collection($warnings);
    }

    private function authorizeSellOrManage(Request $request): void
    {
        $abilities = $request->user()?->abilities() ?? [];
        $allowed = in_array('sales.create', $abilities, true)
            || in_array('products.manage', $abilities, true);

        abort_unless($allowed, 403);
    }
}
