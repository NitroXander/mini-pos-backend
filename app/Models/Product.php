<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use BelongsToShop;

    protected $fillable = [
        'shop_id',
        'sku',
        'name',
        'sell_price_minor',
        'cost_minor',
        'unit',
        'barcode',
        'is_active',
        'stock_on_hand',
    ];

    protected function casts(): array
    {
        return [
            'sell_price_minor' => 'integer',
            'cost_minor' => 'integer',
            'is_active' => 'boolean',
            'stock_on_hand' => 'decimal:3',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'sku' => $this->sku,
            'name' => $this->name,
            'sell_price_minor' => $this->sell_price_minor,
            'cost_minor' => $this->cost_minor,
            'unit' => $this->unit,
            'barcode' => $this->barcode,
            'is_active' => $this->is_active,
            'stock_on_hand' => $this->stock_on_hand,
        ];
    }
}
