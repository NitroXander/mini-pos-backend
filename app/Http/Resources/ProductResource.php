<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'sell_price_minor' => $this->sell_price_minor,
            'cost_minor' => $this->when($request->user()?->seesCost() ?? false, $this->cost_minor),
            'unit' => $this->unit,
            'barcode' => $this->barcode,
            'is_active' => $this->is_active,
            'stock_on_hand' => $this->stock_on_hand,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
