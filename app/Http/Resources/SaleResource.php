<?php

namespace App\Http\Resources;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sale */
class SaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_uuid' => $this->client_uuid,
            'number' => $this->number,
            'cashier_id' => $this->cashier_id,
            'cashier_name' => $this->cashier_name,
            'sold_at' => $this->sold_at?->toISOString(),
            'subtotal_minor' => $this->subtotal_minor,
            'discount_minor' => $this->discount_minor,
            'total_minor' => $this->total_minor,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'stock_warning' => $this->stock_warning,
            'void_reason' => $this->void_reason,
            'voided_at' => $this->voided_at?->toISOString(),
            'lines' => SaleLineResource::collection($this->whenLoaded('lines')),
        ];
    }
}
