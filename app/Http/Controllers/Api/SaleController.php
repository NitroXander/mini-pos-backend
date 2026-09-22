<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\SaleVoidService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class SaleController extends Controller
{
    public function __construct(private readonly SaleVoidService $voids) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($this->canViewSales($user?->abilities() ?? []), 403);

        $input = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:64'],
        ]);

        $user->loadMissing('shop');
        $query = Sale::query()->with('lines')->orderByDesc('sold_at')->orderByDesc('number');

        if ($user->role !== UserRole::Owner) {
            $query->where('cashier_id', $user->id);
        }

        if (! empty($input['date'])) {
            $day = Carbon::parse($input['date'], $user->shop->timezone);
            $query->whereBetween('sold_at', [
                $day->copy()->startOfDay()->utc(),
                $day->copy()->endOfDay()->utc(),
            ]);
        } elseif (empty($input['q'])) {
            $day = Carbon::now($user->shop->timezone);
            $query->whereBetween('sold_at', [
                $day->copy()->startOfDay()->utc(),
                $day->copy()->endOfDay()->utc(),
            ]);
        }

        if (! empty($input['q'])) {
            $term = trim($input['q']);
            $query->where(function ($inner) use ($term) {
                if (ctype_digit($term)) {
                    $inner->orWhere('number', (int) $term);
                }

                $inner->orWhere('cashier_name', 'like', '%'.$term.'%')
                    ->orWhere('client_uuid', 'like', '%'.$term.'%')
                    ->orWhereHas('lines', function ($lines) use ($term) {
                        $lines->where('name', 'like', '%'.$term.'%')
                            ->orWhere('sku', 'like', '%'.$term.'%');
                    });
            });
        }

        return SaleResource::collection($query->limit(100)->get());
    }

    public function show(Request $request, Sale $sale): SaleResource
    {
        $user = $request->user();
        abort_unless($this->canViewSales($user?->abilities() ?? []), 403);

        if ($user->role !== UserRole::Owner && $sale->cashier_id !== $user->id) {
            abort(404);
        }

        return new SaleResource($sale->load('lines'));
    }

    public function void(Request $request, Sale $sale): JsonResponse
    {
        abort_unless(in_array('sales.void', $request->user()?->abilities() ?? [], true), 403);

        $reason = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ])['reason'];

        $voided = $this->voids->void($request->user(), $sale, $reason);

        return (new SaleResource($voided))
            ->response()
            ->setStatusCode($voided->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * @param  list<string>  $abilities
     */
    private function canViewSales(array $abilities): bool
    {
        return in_array('sales.create', $abilities, true)
            || in_array('reports.eod', $abilities, true);
    }
}
