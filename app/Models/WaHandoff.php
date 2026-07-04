<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaHandoff extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id', 'contact_id', 'store_id', 'triggered_by_ai_run_id',
        'reason', 'summary', 'status', 'assigned_user_id',
        'assigned_at', 'resolved_at', 'resolution',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WaConversation::class, 'conversation_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WaContact::class, 'contact_id');
    }

    public function triggerRun(): BelongsTo
    {
        return $this->belongsTo(WaAiRun::class, 'triggered_by_ai_run_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
