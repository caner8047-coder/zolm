<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportSyncCursor extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_channel_id', 'sync_type', 'cursor_value',
        'last_success_at', 'last_error_at', 'last_error_code',
    ];

    protected function casts(): array
    {
        return [
            'last_success_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(SupportChannel::class, 'support_channel_id');
    }
}
