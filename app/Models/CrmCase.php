<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CrmCase extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sla_due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'meta_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'contact_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(CrmTimelineEvent::class, 'case_id')->latest('occurred_at')->latest('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class, 'case_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CrmNote::class, 'case_id')->latest();
    }

    public function priorityTone(): string
    {
        return match ($this->priority) {
            'critical', 'high' => 'danger',
            'normal' => 'warning',
            'low' => 'info',
            default => 'default',
        };
    }
}
