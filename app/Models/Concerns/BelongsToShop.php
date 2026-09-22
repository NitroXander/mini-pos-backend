<?php

namespace App\Models\Concerns;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToShop
{
    public static function bootBelongsToShop(): void
    {
        static::addGlobalScope('shop', function (Builder $builder): void {
            $user = auth()->user();

            if ($user === null) {
                return;
            }

            $builder->where(
                $builder->getModel()->getTable().'.shop_id',
                $user->shop_id,
            );
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('shop_id') !== null) {
                return;
            }

            $shopId = auth()->user()?->shop_id;

            if ($shopId !== null) {
                $model->setAttribute('shop_id', $shopId);
            }
        });
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
