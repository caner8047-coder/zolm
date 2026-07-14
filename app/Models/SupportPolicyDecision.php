<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportPolicyDecision extends Model
{
    protected $fillable = [
        'store_id', 'support_channel_id', 'conversation_id', 'message_id',
        'policy_version', 'channel_key', 'allowed', 'decision_code', 'reason',
        'validator_set_json', 'actor_user_id',
    ];

    protected function casts(): array
    {
        return ['allowed' => 'boolean', 'validator_set_json' => 'array'];
    }
}
