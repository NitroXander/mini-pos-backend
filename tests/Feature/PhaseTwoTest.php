<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhaseTwoTest extends TestCase
{
    use RefreshDatabase;

    public function test_void_restores_stock_and_is_idempotent(): void
    {
        [$owner, $product] = $this->shopWithProduct(stock: 10);
        $token = $owner->createToken('pos')->plainTextToken;

        $this->withToken($token)->postJson('/api/sync/sales', [
            'sales' => [[
                'client_uuid' => (string) Str::uuid(),
                'sold_at' => now()->toISOString(),
                'payment_method' => 'cash',
                'lines' => [[
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'qty' => 3,
                    'unit_price_minor' => 1000,
                ]],
            ]],
        ])->assertOk();

        $saleId = $owner->shop->sales()->first()->id;
        auth()->forgetGuards();

        $this->withToken($token)
            ->postJson("/api/sales/{$saleId}/void", ['reason' => 'Wrong item'])
            ->assertOk()
            ->assertJsonPath('data.status', 'void')
            ->assertJsonPath('data.void_reason', 'Wrong item');

        $this->assertSame('10.000', $product->fresh()->stock_on_hand);

        auth()->forgetGuards();

        $this->withToken($token)
            ->postJson("/api/sales/{$saleId}/void", ['reason' => 'Wrong item'])
            ->assertOk()
            ->assertJsonPath('data.status', 'void');

        $this->assertSame('10.000', $product->fresh()->stock_on_hand);
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_cashier_cannot_void_and_damage_cannot_go_below_zero(): void
    {
        $shop = Shop::factory()->create();
        $cashier = User::factory()->create(['shop_id' => $shop->id, 'role' => UserRole::Cashier]);
        $owner = User::factory()->create(['shop_id' => $shop->id, 'role' => UserRole::Owner]);
        $product = Product::query()->create([
            'shop_id' => $shop->id,
            'sku' => 'TEA',
            'name' => 'Tea',
            'sell_price_minor' => 100,
            'cost_minor' => 50,
            'stock_on_hand' => 1,
        ]);

        $this->withToken($cashier->createToken('pos')->plainTextToken)
            ->postJson('/api/stock-adjustments', [
                'product_id' => $product->id,
                'kind' => 'damage',
                'qty' => 1,
                'reason' => 'Broken',
            ])
            ->assertForbidden();

        auth()->forgetGuards();

        $this->withToken($owner->createToken('pos')->plainTextToken)
            ->postJson('/api/stock-adjustments', [
                'product_id' => $product->id,
                'kind' => 'damage',
                'qty' => 5,
                'reason' => 'Broken',
            ])
            ->assertStatus(422);
    }

    public function test_second_shop_does_not_see_the_first_shops_bills(): void
    {
        $this->artisan('pos:shop', [
            'name' => 'Second Shop',
            '--currency' => 'USD',
            '--locale' => 'en-US',
            '--timezone' => 'UTC',
            '--owner' => 'owner@second.shop',
            '--password' => 'password',
        ])->assertSuccessful();

        $other = User::query()->where('email', 'owner@second.shop')->firstOrFail();

        $this->withToken($other->createToken('pos')->plainTextToken)
            ->getJson('/api/sales')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertSame('USD', $other->shop->currency);
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
}
