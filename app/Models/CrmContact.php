<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmContact extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'first_order_at' => 'datetime',
            'last_order_at' => 'datetime',
            'last_event_at' => 'datetime',
            'gross_revenue_total' => 'decimal:2',
            'tags_json' => 'array',
            'meta_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function identities(): HasMany
    {
        return $this->hasMany(CrmContactIdentity::class, 'contact_id');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CrmCase::class, 'contact_id');
    }

    public function openCases(): HasMany
    {
        return $this->cases()->whereIn('status', ['open', 'pending', 'in_progress']);
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(CrmTimelineEvent::class, 'contact_id')->latest('occurred_at')->latest('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class, 'contact_id');
    }

    public function openTasks(): HasMany
    {
        return $this->tasks()->whereIn('status', ['open', 'pending']);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CrmNote::class, 'contact_id')->latest();
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CrmCustomerLedgerEntry::class, 'contact_id')->latest('purchased_at')->latest('id');
    }

    public function riskTone(): string
    {
        return match (true) {
            $this->risk_score >= 70 => 'danger',
            $this->risk_score >= 40 => 'warning',
            $this->risk_score >= 15 => 'info',
            default => 'success',
        };
    }

    public function valueTone(): string
    {
        return match (true) {
            $this->value_score >= 70 => 'success',
            $this->value_score >= 35 => 'info',
            default => 'default',
        };
    }
}
