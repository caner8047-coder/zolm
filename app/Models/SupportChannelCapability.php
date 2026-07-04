<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportChannelCapability extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_channel_id', 'capability', 'status', 'source',
        'checked_at', 'details_json',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'details_json' => 'array',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(SupportChannel::class, 'support_channel_id');
    }
}
