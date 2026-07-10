<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EDocumentEvent extends Model
{
    protected $table = 'e_document_events';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'occurred_at'   => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(EDocument::class, 'e_document_id');
    }

    // Geriye dönük uyumluluk için eski metot
    public function eDocument(): BelongsTo
    {
        return $this->document();
    }
}
