<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportInternalNote extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'note_encrypted',
    ];

    protected $casts = [
        'note_encrypted' => 'encrypted',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
