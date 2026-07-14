<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportReplyMacroVersion extends Model
{
    protected $fillable = [
        'macro_id',
        'user_id',
        'body_before',
        'body_after',
        'action',
    ];

    public function macro(): BelongsTo
    {
        return $this->belongsTo(SupportReplyMacro::class, 'macro_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
