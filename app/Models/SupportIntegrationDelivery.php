<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportIntegrationDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_integration_event_id', 'integration_connection_id', 'webhook_url', 'operation_path', 'status',
        'attempts', 'last_attempt_at', 'last_error',
    ];

    protected $casts = [
        'last_attempt_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(SupportIntegrationEvent::class, 'support_integration_event_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }
}
