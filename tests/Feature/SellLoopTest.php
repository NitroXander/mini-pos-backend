<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SellLoopTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_assigns_a_bill_number_and_is_idempotent(): void
    {
        [$owner, $product] = $this->shopWithProduct(stock: 10);
        $uuid = (string) Str::uuid();
        $body = $this->saleBody($uuid, $product->id, qty: 2, price: 25000);

        $first = $this->withToken($owner->createToken('pos')->plainTextToken)
            ->postJson('/api/sync/sales', $body);

        $first->assertOk()
            ->assertJsonPath('results.0.accepted', true)
            ->assertJsonPath('results.0.duplicate', false)
            ->assertJsonPath('results.0.number', 1)
            ->assertJsonPath('results.0.stock_warning', false);

        $this->assertSame('8.000', $product->fresh()->stock_on_hand);

        $second = $this->withToken($owner->createToken('pos')->plainTextToken)
            ->postJson('/api/sync/sales', $body);

        $second->assertOk()
            ->assertJsonPath('results.0.duplicate', true)
            ->assertJsonPath('results.0.number', 1);

        $this->assertSame('8.000', $product->fresh()->stock_on_hand);
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_negative_stock_still_accepts_the_sale_and_flags_a_warning(): void
    {
        [$owner, $product] = $this->shopWithProduct(stock: 1);
        $token = $owner->createToken('pos')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/sync/sales', $this->saleBody((string) Str::uuid(), $product->id, qty: 3, price: 1000))
            ->assertOk()
            ->assertJsonPath('results.0.accepted', true)
            ->assertJsonPath('results.0.stock_warning', true);

        $this->assertSame('-2.000', $product->fresh()->stock_on_hand);
    }

    public function test_owner_can_close_the_day_once_and_cashier_cannot(): void
    {
        $shop = Shop::factory()->create(['timezone' => 'UTC', 'currency' => 'LKR']);
        $owner = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => UserRole::Owner,
        ]);
        $cashier = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => UserRole::Cashier,
        ]);
        $product = Product::query()->create([
            'shop_id' => $shop->id,
            'sku' => 'TEA',
            'name' => 'Tea',
            'sell_price_minor' => 25000,
            'cost_minor' => 18000,
            'stock_on_hand' => 10,
        ]);

        $this->withToken($owner->createToken('pos')->plainTextToken)
            ->postJson('/api/sync/sales', [
                'sales' => [[
                    'client_uuid' => (string) Str::uuid(),
                    'sold_at' => now()->toISOString(),
                    'discount_minor' => 5000,
                    'payment_method' => 'cash',
                    'lines' => [[
                        'product_id' => $product->id,
                        'sku' => $product->sku,
                        'name' => $product->name,
                        'qty' => 1,
                        'unit_price_minor' => 25000,
                    ]],
                ]],
            ])
            ->assertOk();

        $date = now()->toDateString();
        $ownerToken = $owner->createToken('pos')->plainTextToken;

        auth()->forgetGuards();

        $this->withToken($cashier->createToken('pos')->plainTextToken)
            ->postJson('/api/eod', ['date' => $date])
            ->assertForbidden();

        auth()->forgetGuards();

        $closed = $this->withToken($ownerToken)->postJson('/api/eod', ['date' => $date]);

        $closed->assertCreated()
            ->assertJsonPath('data.closed.sales_count', 1)
            ->assertJsonPath('data.closed.revenue_minor', 20000)
            ->assertJsonPath('data.closed.cogs_minor', 18000)
            ->assertJsonPath('data.closed.gross_profit_minor', 2000);

        $this->withToken($ownerToken)
            ->postJson('/api/eod', ['date' => $date])
            ->assertOk()
            ->assertJsonPath('data.already_closed', true);

        $this->assertDatabaseCount('eod_reports', 1);
    }

    /**
     * @return array{0: User, 1: Product}
     */
    private function shopWithProduct(int $stock): array
    {
        $shop = Shop::factory()->create(['timezone' => 'UTC']);
        $owner = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => UserRole::Owner,
        ]);
        $product = Product::query()->create([
            'shop_id' => $shop->id,
            'sku' => 'RICE',
            'name' => 'Rice',
            'sell_price_minor' => 25000,
            'cost_minor' => 18000,
            'stock_on_hand' => $stock,
        ]);

        return [$owner, $product];
    }

    /**
     * @return array<string, mixed>
     */
    private function saleBody(string $uuid, int $productId, float $qty, int $price): array
    {
        return [
            'sales' => [[
                'client_uuid' => $uuid,
                'sold_at' => now()->toISOString(),
                'payment_method' => 'cash',
                'lines' => [[
                    'product_id' => $productId,
                    'sku' => 'RICE',
                    'name' => 'Rice',
                    'qty' => $qty,
                    'unit_price_minor' => $price,
                ]],
            ]],
        ];
    }
}
