<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlaTrack extends Model
{
    use HasFactory;

    protected $fillable = [
        'sla_definition_id', 'conversation_id', 'store_id', 'status',
        'started_at', 'first_response_at',
        'resolution_deadline', 'first_response_deadline',
        'first_response_breached', 'resolution_breached',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'first_response_at' => 'datetime',
            'resolution_deadline' => 'datetime',
            'first_response_deadline' => 'datetime',
            'first_response_breached' => 'boolean',
            'resolution_breached' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(SlaDefinition::class, 'sla_definition_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SlaEvent::class, 'sla_track_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
