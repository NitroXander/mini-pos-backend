<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentService
{
    public function adjust(User $actor, Product $product, string $kind, string $qty, string $reason): StockAdjustment
    {
        return DB::transaction(function () use ($actor, $product, $kind, $qty, $reason): StockAdjustment {
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $before = number_format((float) $product->stock_on_hand, 3, '.', '');
            $amount = number_format((float) $qty, 3, '.', '');

            $after = match ($kind) {
                'receive' => bcadd($before, $amount, 3),
                'damage' => bcsub($before, $amount, 3),
                'count' => $amount,
                default => throw ValidationException::withMessages([
                    'kind' => 'Choose receive, damage, or count.',
                ]),
            };

            if (bccomp($after, '0', 3) < 0) {
                throw ValidationException::withMessages([
                    'qty' => 'That would take stock below zero. Use a stock count to correct it.',
                ]);
            }

            $product->stock_on_hand = $after;
            $product->save();

            $adjustment = StockAdjustment::query()->create([
                'shop_id' => $actor->shop_id,
                'product_id' => $product->id,
                'user_id' => $actor->id,
                'kind' => $kind,
                'qty' => $amount,
                'stock_before' => $before,
                'stock_after' => $after,
                'reason' => $reason,
            ]);

            AuditLogger::record(
                $actor,
                'stock.adjusted',
                'product',
                $product->id,
                before: ['stock_on_hand' => $before],
                after: [
                    'kind' => $kind,
                    'qty' => $amount,
                    'stock_on_hand' => $after,
                    'reason' => $reason,
                ],
            );

            return $adjustment;
        });
    }
}
