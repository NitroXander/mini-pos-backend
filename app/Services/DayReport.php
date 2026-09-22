<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DayReport
{
    /**
     * @return array{sales: Collection<int, Sale>, sales_count: int, revenue_minor: int, cogs_minor: int, gross_profit_minor: int, void_count: int, stock: array<int, array<string, mixed>>}
     */
    public function forShopDate(Shop $shop, string $date): array
    {
        [$start, $end] = $this->bounds($shop, $date);

        $sales = Sale::query()
            ->with('lines')
            ->whereBetween('sold_at', [$start, $end])
            ->orderBy('number')
            ->get();

        $completed = $sales->where('status', 'completed');
        $revenue = (int) $completed->sum('total_minor');
        $cogs = 0;

        foreach ($completed as $sale) {
            foreach ($sale->lines as $line) {
                $cogs += (int) round((float) bcmul((string) $line->qty, (string) $line->cost_minor, 4));
            }
        }

        $stock = Product::query()
            ->orderBy('name')
            ->get(['sku', 'name', 'stock_on_hand'])
            ->map(fn (Product $product) => [
                'sku' => $product->sku,
                'name' => $product->name,
                'stock_on_hand' => $product->stock_on_hand,
            ])
            ->all();

        return [
            'sales' => $sales,
            'sales_count' => $completed->count(),
            'revenue_minor' => $revenue,
            'cogs_minor' => $cogs,
            'gross_profit_minor' => $revenue - $cogs,
            'void_count' => $sales->where('status', 'void')->count(),
            'stock' => $stock,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function bounds(Shop $shop, string $date): array
    {
        $day = Carbon::parse($date, $shop->timezone);

        return [
            $day->copy()->startOfDay()->utc(),
            $day->copy()->endOfDay()->utc(),
        ];
    }
}
