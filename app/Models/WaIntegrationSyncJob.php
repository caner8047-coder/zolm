<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaIntegrationSyncJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'integration_id', 'job_type', 'status',
        'payload_json', 'result_json', 'error_message',
        'retry_count', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'result_json' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(WaExternalIntegration::class, 'integration_id');
    }
}
