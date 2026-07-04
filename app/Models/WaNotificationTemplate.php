<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaNotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id', 'key', 'name', 'subject', 'body_template',
        'variables_schema', 'status',
    ];

    protected function casts(): array
    {
        return ['variables_schema' => 'array'];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(WaNotificationChannel::class, 'channel_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
