<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'shop_id', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function managesProducts(): bool
    {
        return in_array($this->role, [UserRole::Owner, UserRole::Inventory], true);
    }

    public function seesCost(): bool
    {
        return $this->role === UserRole::Owner;
    }

    /**
     * @return list<string>
     */
    public function abilities(): array
    {
        return match ($this->role) {
            UserRole::Owner => [
                'products.manage',
                'costs.view',
                'stock.adjust',
                'sales.create',
                'sales.void',
                'reports.eod',
                'users.manage',
                'audit.view',
            ],
            UserRole::Cashier => [
                'sales.create',
            ],
            UserRole::Inventory => [
                'products.manage',
                'stock.adjust',
            ],
        };
    }
}
