<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportDispatchAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_dispatch_id',
        'attempted_at',
        'status',
        'error_message',
        'latency_ms',
    ];

    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
            'latency_ms' => 'integer',
        ];
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(SupportDispatch::class, 'support_dispatch_id');
    }
}
