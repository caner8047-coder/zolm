<?php

namespace App\Modules\Hr\Core\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use Illuminate\Database\Eloquent\Model;

class HrIntegrationOutbox extends Model
{
    use BelongsToLegalEntity;

    protected $table = 'hr_integration_outbox';
    protected $fillable = ['legal_entity_id', 'target', 'event_type', 'source_type', 'source_id', 'source_key', 'payload_hash', 'payload', 'status', 'attempt_count', 'processed_at', 'last_error', 'created_by'];
    protected function casts(): array { return ['payload' => 'array', 'attempt_count' => 'integer', 'processed_at' => 'datetime']; }
}
