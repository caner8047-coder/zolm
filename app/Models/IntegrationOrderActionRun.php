<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationOrderActionRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'channel_order_id',
        'channel_order_package_id',
        'triggered_by',
        'action_type',
        'status',
        'attempt_count',
        'external_action_id',
        'request_context_json',
        'response_json',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'request_context_json' => 'array',
            'response_json' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ChannelOrder::class, 'channel_order_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ChannelOrderPackage::class, 'channel_order_package_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
