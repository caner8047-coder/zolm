<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMatchIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'channel_listing_id',
        'match_status',
        'match_reason',
        'candidate_ids_json',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'candidate_ids_json' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function channelListing(): BelongsTo
    {
        return $this->belongsTo(ChannelListing::class, 'channel_listing_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
