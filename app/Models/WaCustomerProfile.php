<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaCustomerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id', 'store_id',
        'total_orders', 'total_revenue', 'avg_order_value',
        'total_messages_sent', 'total_messages_delivered', 'total_messages_read',
        'total_clicks', 'total_coupons_used',
        'first_order_at', 'last_order_at', 'last_message_at', 'last_click_at',
        'engagement_score', 'segment_tags',
    ];

    protected function casts(): array
    {
        return [
            'total_revenue' => 'decimal:2',
            'avg_order_value' => 'decimal:2',
            'first_order_at' => 'datetime',
            'last_order_at' => 'datetime',
            'last_message_at' => 'datetime',
            'last_click_at' => 'datetime',
            'segment_tags' => 'array',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WaContact::class, 'contact_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }
}
