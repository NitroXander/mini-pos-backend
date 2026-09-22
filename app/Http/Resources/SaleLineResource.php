<?php

namespace App\Http\Resources;

use App\Models\SaleLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SaleLine */
class SaleLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'name' => $this->name,
            'unit' => $this->unit,
            'qty' => $this->qty,
            'unit_price_minor' => $this->unit_price_minor,
            'cost_minor' => $this->when($request->user()?->seesCost() ?? false, $this->cost_minor),
            'line_total_minor' => $this->line_total_minor,
        ];
    }
}
