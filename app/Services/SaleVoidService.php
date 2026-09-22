<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

class SaleVoidService
{
    public function void(User $actor, Sale $sale, string $reason): Sale
    {
        return DB::transaction(function () use ($actor, $sale, $reason): Sale {
            $sale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $sale->load('lines');

            if ($sale->status === 'void') {
                return $sale;
            }

            foreach ($sale->lines as $line) {
                if ($line->product_id === null) {
                    continue;
                }

                $product = Product::query()->whereKey($line->product_id)->lockForUpdate()->first();

                if ($product === null) {
                    continue;
                }

                $product->stock_on_hand = bcadd((string) $product->stock_on_hand, (string) $line->qty, 3);
                $product->save();
            }

            $sale->forceFill([
                'status' => 'void',
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_reason' => $reason,
            ])->save();

            AuditLogger::record(
                $actor,
                'sale.voided',
                'sale',
                $sale->id,
                before: ['status' => 'completed'],
                after: [
                    'status' => 'void',
                    'number' => $sale->number,
                    'reason' => $reason,
                ],
            );

            return $sale->load('lines');
        });
    }
}
