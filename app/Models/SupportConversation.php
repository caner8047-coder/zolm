<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_channel_id', 'external_conversation_id', 'external_customer_id', 'external_customer_hash',
        'store_id', 'source_type', 'status', 'priority', 'assigned_user_id', 'support_team_id',
        'last_message_at', 'last_inbound_at', 'last_outbound_at',
        'ai_mode', 'ownership_status', 'version', 'source_reference_json',
    ];

    protected function casts(): array
    {
        return [
            'external_customer_id' => 'encrypted',
            'source_reference_json' => 'array',
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'last_outbound_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SupportConversation $conversation): void {
            $customerId = trim((string) ($conversation->external_customer_id ?? ''));
            $conversation->external_customer_hash = $customerId !== ''
                ? hash('sha256', $customerId)
                : null;
        });
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(SupportChannel::class, 'support_channel_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function supportTeam(): BelongsTo
    {
        return $this->belongsTo(SupportTeam::class, 'support_team_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'conversation_id');
    }

    public function agentActions(): HasMany
    {
        return $this->hasMany(SupportAgentAction::class, 'conversation_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_user_id');
    }

    /**
     * Temsilci tarafından konuşmayı sahiplenme (Claim)
     */
    public function claim(int $userId): bool
    {
        $currentVersion = $this->version ?? 1;

        $updated = self::where('id', $this->id)
            ->where('version', $currentVersion)
            ->update([
                'assigned_user_id' => $userId,
                'ownership_status' => 'human',
                'version' => $currentVersion + 1,
            ]);

        if ($updated) {
            $this->assigned_user_id = $userId;
            $this->ownership_status = 'human';
            $this->version = $currentVersion + 1;
            return true;
        }

        return false;
    }

    /**
     * Konuşma sahipliğini AI'a geri bırakma (Release to AI)
     */
    public function releaseToAi(): bool
    {
        $currentVersion = $this->version ?? 1;

        $updated = self::where('id', $this->id)
            ->where('version', $currentVersion)
            ->update([
                'assigned_user_id' => null,
                'ownership_status' => 'ai',
                'version' => $currentVersion + 1,
            ]);

        if ($updated) {
            $this->assigned_user_id = null;
            $this->ownership_status = 'ai';
            $this->version = $currentVersion + 1;
            return true;
        }

        return false;
    }

    /**
     * Konuşmayı çözüldü olarak işaretleme (Resolve)
     */
    public function markAsResolved(): bool
    {
        $currentVersion = $this->version ?? 1;

        $updated = self::where('id', $this->id)
            ->where('version', $currentVersion)
            ->update([
                'status' => 'resolved',
                'version' => $currentVersion + 1,
            ]);

        if ($updated) {
            $this->status = 'resolved';
            $this->version = $currentVersion + 1;
            return true;
        }

        return false;
    }
}
