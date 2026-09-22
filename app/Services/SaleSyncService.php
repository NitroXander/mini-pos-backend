<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SaleSyncService
{
    /**
     * Accept one offline bill. Retries with the same client UUID are idempotent.
     *
     * @param  array<string, mixed>  $payload
     * @return array{client_uuid: string, accepted: bool, duplicate: bool, number: int|null, stock_warning: bool, message: string|null}
     */
    public function accept(User $actor, array $payload): array
    {
        $clientUuid = (string) $payload['client_uuid'];

        $existing = Sale::query()->where('client_uuid', $clientUuid)->first();

        if ($existing !== null) {
            return $this->result($existing, duplicate: true);
        }

        $cashier = $this->resolveCashier($actor, $payload['cashier_id'] ?? null);

        if ($cashier === null) {
            return $this->rejected($clientUuid, 'Cashier is not in this shop.');
        }

        try {
            $sale = DB::transaction(function () use ($actor, $cashier, $payload, $clientUuid): Sale {
                $already = Sale::query()->where('client_uuid', $clientUuid)->lockForUpdate()->first();

                if ($already !== null) {
                    return $already;
                }

                $shop = Shop::query()->whereKey($actor->shop_id)->lockForUpdate()->firstOrFail();
                $shop->bill_sequence++;
                $shop->save();

                $built = $this->applyDiscount($payload, $this->buildLines($payload['lines']));

                $sale = Sale::query()->create([
                    'shop_id' => $actor->shop_id,
                    'client_uuid' => $clientUuid,
                    'number' => $shop->bill_sequence,
                    'cashier_id' => $cashier->id,
                    'cashier_name' => $cashier->name,
                    'sold_at' => Carbon::parse($payload['sold_at']),
                    'subtotal_minor' => $built['subtotal_minor'],
                    'discount_minor' => $built['discount_minor'],
                    'total_minor' => $built['total_minor'],
                    'payment_method' => $payload['payment_method'] ?? 'cash',
                    'status' => 'completed',
                    'stock_warning' => $built['stock_warning'],
                ]);

                $sale->lines()->createMany($built['lines']);

                AuditLogger::record(
                    $actor,
                    'sale.created',
                    'sale',
                    $sale->id,
                    after: [
                        'client_uuid' => $sale->client_uuid,
                        'number' => $sale->number,
                        'cashier_id' => $sale->cashier_id,
                        'total_minor' => $sale->total_minor,
                        'stock_warning' => $sale->stock_warning,
                    ],
                );

                return $sale;
            });
        } catch (QueryException $exception) {
            $sale = Sale::query()->where('client_uuid', $clientUuid)->first();

            if ($sale !== null && $this->isDuplicate($exception)) {
                return $this->result($sale, duplicate: true);
            }

            throw $exception;
        }

        return $this->result($sale, duplicate: false);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{lines: array<int, array<string, mixed>>, subtotal_minor: int, discount_minor: int, total_minor: int, stock_warning: bool}
     */
    private function buildLines(array $lines): array
    {
        $stored = [];
        $subtotal = 0;
        $warning = false;

        foreach ($lines as $line) {
            $qty = number_format((float) $line['qty'], 3, '.', '');
            $unitPrice = (int) $line['unit_price_minor'];
            $lineTotal = (int) round((float) bcmul($qty, (string) $unitPrice, 4));
            $product = isset($line['product_id'])
                ? Product::query()->whereKey($line['product_id'])->lockForUpdate()->first()
                : null;

            $cost = 0;

            if ($product === null) {
                $warning = true;
            } else {
                $cost = $product->cost_minor;
                $next = bcsub((string) $product->stock_on_hand, $qty, 3);

                if (bccomp($next, '0', 3) < 0) {
                    $warning = true;
                }

                $product->stock_on_hand = $next;
                $product->save();
            }

            $stored[] = [
                'product_id' => $product?->id,
                'sku' => $line['sku'],
                'name' => $line['name'],
                'unit' => $line['unit'] ?? $product?->unit ?? 'pcs',
                'qty' => $qty,
                'unit_price_minor' => $unitPrice,
                'cost_minor' => $cost,
                'line_total_minor' => $lineTotal,
            ];
            $subtotal += $lineTotal;
        }

        return [
            'lines' => $stored,
            'subtotal_minor' => $subtotal,
            'discount_minor' => 0,
            'total_minor' => $subtotal,
            'stock_warning' => $warning,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{lines: array<int, array<string, mixed>>, subtotal_minor: int, discount_minor: int, total_minor: int, stock_warning: bool}  $built
     * @return array{lines: array<int, array<string, mixed>>, subtotal_minor: int, discount_minor: int, total_minor: int, stock_warning: bool}
     */
    private function applyDiscount(array $payload, array $built): array
    {
        $discount = (int) ($payload['discount_minor'] ?? 0);
        $built['discount_minor'] = $discount;
        $built['total_minor'] = $built['subtotal_minor'] - $discount;

        return $built;
    }

    private function resolveCashier(User $actor, mixed $cashierId): ?User
    {
        if ($cashierId === null || (int) $cashierId === $actor->id) {
            return $actor;
        }

        if ($actor->role !== UserRole::Owner) {
            return null;
        }

        return User::query()
            ->where('shop_id', $actor->shop_id)
            ->whereKey($cashierId)
            ->first();
    }

    /**
     * @return array{client_uuid: string, accepted: bool, duplicate: bool, number: int|null, stock_warning: bool, message: string|null}
     */
    private function result(Sale $sale, bool $duplicate): array
    {
        return [
            'client_uuid' => $sale->client_uuid,
            'accepted' => true,
            'duplicate' => $duplicate,
            'number' => $sale->number,
            'stock_warning' => $sale->stock_warning,
            'message' => null,
        ];
    }

    /**
     * @return array{client_uuid: string, accepted: bool, duplicate: bool, number: int|null, stock_warning: bool, message: string|null}
     */
    private function rejected(string $clientUuid, string $message): array
    {
        return [
            'client_uuid' => $clientUuid,
            'accepted' => false,
            'duplicate' => false,
            'number' => null,
            'stock_warning' => false,
            'message' => $message,
        ];
    }

    private function isDuplicate(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return $sqlState === '23000';
    }
}
