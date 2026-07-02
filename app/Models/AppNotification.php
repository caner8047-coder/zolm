<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AppNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id',
        'type',
        'severity',
        'event_key',
        'title',
        'body',
        'subject_type',
        'subject_id',
        'data_json',
        'action_url',
        'read_at',
        'seen_at',
        'triggered_at',
        'email_digest_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'data_json' => 'array',
            'read_at' => 'datetime',
            'seen_at' => 'datetime',
            'triggered_at' => 'datetime',
            'email_digest_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
