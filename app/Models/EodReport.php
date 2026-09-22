<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EodReport extends Model
{
    use BelongsToShop;

    protected $fillable = [
        'shop_id',
        'report_date',
        'closed_by',
        'opening_notes',
        'closing_notes',
        'sales_count',
        'revenue_minor',
        'cogs_minor',
        'gross_profit_minor',
        'void_count',
        'stock_summary',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'sales_count' => 'integer',
            'revenue_minor' => 'integer',
            'cogs_minor' => 'integer',
            'gross_profit_minor' => 'integer',
            'void_count' => 'integer',
            'stock_summary' => 'array',
        ];
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
