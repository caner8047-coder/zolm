<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ReturnWhatsappMessage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'payload_json' => 'array',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ReturnWhatsappThread::class, 'return_whatsapp_thread_id');
    }

    public function intakeMedia(): BelongsTo
    {
        return $this->belongsTo(ReturnIntakeMedia::class, 'return_intake_media_id');
    }

    public function mediaUrl(): ?string
    {
        if ($this->intakeMedia) {
            return $this->intakeMedia->publicUrl();
        }

        if (!$this->media_disk || !$this->media_path) {
            return null;
        }

        try {
            return Storage::disk($this->media_disk)->url($this->media_path);
        } catch (\Throwable) {
            return null;
        }
    }
}
