<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    protected $fillable = [
        'user_id',
        'profile_id',
        'original_filename',
        'status',
        'error_message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ReportFile::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(AIConversation::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }
}
