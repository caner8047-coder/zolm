<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnBridgeSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'whatsapp_bridge_enabled' => 'boolean',
            'verify_token' => 'encrypted',
            'access_token' => 'encrypted',
            'app_secret' => 'encrypted',
            'message_window_minutes' => 'integer',
        ];
    }

    public function systemUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'system_user_id');
    }
}
