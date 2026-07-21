<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HepsiburadaReadinessAudit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'correlation_id',
        'store_id',
        'connection_id',
        'acting_user_id',
        'reason',
        'tenant_user_id',
        'release_sha',
        'runtime_id',
        'operation',
        'confirm_read',
        'rollout_gate',
        'http_attempted',
        'http_status',
        'provider_error_code',
        'duration_ms',
        'item_count',
        'db_mutation_count',
        'decision',
    ];

    protected $casts = [
        'confirm_read'      => 'boolean',
        'rollout_gate'      => 'boolean',
        'http_attempted'    => 'boolean',
        'http_status'       => 'integer',
        'duration_ms'       => 'integer',
        'item_count'        => 'integer',
        'db_mutation_count' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'connection_id');
    }

    public function actingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acting_user_id');
    }
}
