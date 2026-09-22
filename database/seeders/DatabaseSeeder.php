<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo shop for local development. Password is intentionally obvious.
     */
    public function run(): void
    {
        $shop = Shop::query()->updateOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'Demo Shop',
                'timezone' => 'Asia/Colombo',
                'currency' => 'LKR',
                'locale' => 'en-LK',
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'owner@demo.shop'],
            [
                'shop_id' => $shop->id,
                'name' => 'Demo Owner',
                'password' => Hash::make('password'),
                'role' => UserRole::Owner,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'inventory@demo.shop'],
            [
                'shop_id' => $shop->id,
                'name' => 'Demo Inventory',
                'password' => Hash::make('password'),
                'role' => UserRole::Inventory,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'cashier@demo.shop'],
            [
                'shop_id' => $shop->id,
                'name' => 'Demo Cashier',
                'password' => Hash::make('password'),
                'role' => UserRole::Cashier,
            ],
        );

        $catalog = [
            ['sku' => 'RICE-1KG', 'name' => 'Rice 1kg', 'sell_price_minor' => 25000, 'cost_minor' => 18000, 'stock_on_hand' => 40],
            ['sku' => 'MILK-400', 'name' => 'Milk 400ml', 'sell_price_minor' => 18000, 'cost_minor' => 14000, 'stock_on_hand' => 24],
            ['sku' => 'BREAD', 'name' => 'Bread loaf', 'sell_price_minor' => 16000, 'cost_minor' => 11000, 'stock_on_hand' => 12],
        ];

        foreach ($catalog as $item) {
            Product::query()->updateOrCreate(
                ['shop_id' => $shop->id, 'sku' => $item['sku']],
                [
                    'name' => $item['name'],
                    'sell_price_minor' => $item['sell_price_minor'],
                    'cost_minor' => $item['cost_minor'],
                    'unit' => 'pcs',
                    'is_active' => true,
                    'stock_on_hand' => $item['stock_on_hand'],
                ],
            );
        }
    }
}
