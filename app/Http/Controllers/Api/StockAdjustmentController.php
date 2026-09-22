<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Services\StockAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockAdjustmentController extends Controller
{
    public function __construct(private readonly StockAdjustmentService $adjustments) {}

    public function store(Request $request): JsonResponse
    {
        abort_unless(in_array('stock.adjust', $request->user()?->abilities() ?? [], true), 403);

        $input = $request->validate([
            'product_id' => ['required', 'integer'],
            'kind' => ['required', 'in:receive,damage,count'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $product = Product::query()->whereKey($input['product_id'])->first();

        if ($product === null) {
            abort(404, 'Product not found.');
        }

        $adjustment = $this->adjustments->adjust(
            $request->user(),
            $product,
            $input['kind'],
            (string) $input['qty'],
            $input['reason'],
        );

        return response()->json([
            'data' => [
                'id' => $adjustment->id,
                'kind' => $adjustment->kind,
                'qty' => $adjustment->qty,
                'stock_before' => $adjustment->stock_before,
                'stock_after' => $adjustment->stock_after,
                'reason' => $adjustment->reason,
            ],
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless(in_array('stock.adjust', $request->user()?->abilities() ?? [], true), 403);

        $rows = StockAdjustment::query()
            ->with(['product:id,sku,name', 'user:id,name'])
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(fn (StockAdjustment $row) => [
                'id' => $row->id,
                'kind' => $row->kind,
                'qty' => $row->qty,
                'stock_before' => $row->stock_before,
                'stock_after' => $row->stock_after,
                'reason' => $row->reason,
                'created_at' => $row->created_at?->toISOString(),
                'product' => $row->product?->only(['sku', 'name']),
                'user' => $row->user?->name,
            ]);

        return response()->json(['data' => $rows]);
    }
}
