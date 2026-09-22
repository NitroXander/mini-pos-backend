<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProvisionShop extends Command
{
    protected $signature = 'pos:shop
        {name : Shop display name}
        {--slug= : Unique slug, derived from the name if omitted}
        {--currency=LKR : ISO currency code}
        {--locale=en-LK : Locale used to format money and dates}
        {--timezone=Asia/Colombo : Shop timezone}
        {--owner= : Owner email}
        {--owner-name=Owner : Owner display name}
        {--password= : Owner password}
        {--cashier= : Optional cashier email}
        {--inventory= : Optional inventory email}';

    protected $description = 'Create a shop and its first users (shared-host onboarding)';

    public function handle(): int
    {
        $ownerEmail = (string) $this->option('owner');
        $password = (string) $this->option('password');

        if ($ownerEmail === '' || $password === '') {
            $this->error('Pass --owner and --password.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $ownerEmail)->exists()) {
            $this->error('That owner email is already in use.');

            return self::FAILURE;
        }

        $name = (string) $this->argument('name');
        $slug = (string) ($this->option('slug') ?: Str::slug($name));

        if (Shop::query()->where('slug', $slug)->exists()) {
            $this->error("Slug {$slug} is already taken. Pass --slug.");

            return self::FAILURE;
        }

        $shop = Shop::query()->create([
            'name' => $name,
            'slug' => $slug,
            'timezone' => (string) $this->option('timezone'),
            'currency' => strtoupper((string) $this->option('currency')),
            'locale' => (string) $this->option('locale'),
        ]);

        $this->createUser($shop, $ownerEmail, (string) $this->option('owner-name'), $password, UserRole::Owner);

        foreach (['cashier' => UserRole::Cashier, 'inventory' => UserRole::Inventory] as $option => $role) {
            $email = (string) $this->option($option);

            if ($email === '') {
                continue;
            }

            $this->createUser($shop, $email, Str::headline($option), $password, $role);
        }

        $this->info("Shop {$shop->name} ({$shop->currency}, {$shop->locale}) is ready. id={$shop->id}");

        return self::SUCCESS;
    }

    private function createUser(Shop $shop, string $email, string $name, string $password, UserRole $role): void
    {
        User::query()->create([
            'shop_id' => $shop->id,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
        ]);
    }
}
