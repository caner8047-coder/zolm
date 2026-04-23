<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnWhatsappThread extends Model
{
    public const STATUS_LABELS = [
        'collecting' => 'Mesaj topluyor',
        'queued' => 'Analiz kuyruğunda',
        'completed' => 'Tamamlandı',
        'archived' => 'Arşivlendi',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'analysis_requested_at' => 'datetime',
            'raw_context_json' => 'array',
        ];
    }

    public function intakeBatch(): BelongsTo
    {
        return $this->belongsTo(ReturnIntakeBatch::class, 'return_intake_batch_id');
    }

    public function intakeItem(): BelongsTo
    {
        return $this->belongsTo(ReturnIntakeItem::class, 'return_intake_item_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ReturnWhatsappMessage::class, 'return_whatsapp_thread_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
