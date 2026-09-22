<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_login_returns_shop_currency_and_can_create_a_product(): void
    {
        $shop = Shop::factory()->create([
            'currency' => 'LKR',
            'locale' => 'en-LK',
        ]);
        User::factory()->create([
            'shop_id' => $shop->id,
            'email' => 'owner@example.test',
            'role' => UserRole::Owner,
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'owner@example.test',
            'password' => 'password',
        ]);

        $login->assertOk()
            ->assertJsonPath('user.role', 'owner')
            ->assertJsonPath('user.shop.currency', 'LKR')
            ->assertJsonPath('user.shop.locale', 'en-LK');

        $this->withToken($login->json('token'))
            ->postJson('/api/products', [
                'sku' => 'TEA',
                'name' => 'Tea',
                'sell_price_minor' => 8000,
                'cost_minor' => 5000,
                'stock_on_hand' => 10,
            ])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'TEA')
            ->assertJsonPath('data.cost_minor', 5000)
            ->assertJsonPath('data.sell_price_minor', 8000);
    }

    public function test_cashier_cannot_see_cost_or_manage_products(): void
    {
        $shop = Shop::factory()->create();
        $cashier = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => UserRole::Cashier,
        ]);
        Product::query()->create([
            'shop_id' => $shop->id,
            'sku' => 'RICE',
            'name' => 'Rice',
            'sell_price_minor' => 25000,
            'cost_minor' => 18000,
            'stock_on_hand' => 4,
        ]);

        $token = $cashier->createToken('pos')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.sku', 'RICE')
            ->assertJsonMissingPath('data.0.cost_minor');

        $this->withToken($token)
            ->postJson('/api/products', [
                'sku' => 'NOPE',
                'name' => 'Nope',
                'sell_price_minor' => 100,
                'cost_minor' => 50,
                'stock_on_hand' => 1,
            ])
            ->assertForbidden();
    }

    public function test_products_are_scoped_to_the_authenticated_shop(): void
    {
        $shop = Shop::factory()->create();
        $other = Shop::factory()->create();
        $owner = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => UserRole::Owner,
        ]);
        Product::query()->create([
            'shop_id' => $other->id,
            'sku' => 'OTHER',
            'name' => 'Other shop item',
            'sell_price_minor' => 100,
            'cost_minor' => 50,
            'stock_on_hand' => 1,
        ]);

        $this->withToken($owner->createToken('pos')->plainTextToken)
            ->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
