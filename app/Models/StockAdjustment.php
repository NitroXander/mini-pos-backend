<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    use BelongsToShop;

    public const UPDATED_AT = null;

    protected $fillable = [
        'shop_id',
        'product_id',
        'user_id',
        'kind',
        'qty',
        'stock_before',
        'stock_after',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'stock_before' => 'decimal:3',
            'stock_after' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
