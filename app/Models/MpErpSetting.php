<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpErpSetting extends Model
{
    protected $fillable = [
        'user_id',
        'provider_name',
        'webhook_url',
        'api_key',
        'api_secret',
        'auto_push_on_reconcile',
        'is_active',
    ];

    protected $casts = [
        'auto_push_on_reconcile' => 'boolean',
        'is_active' => 'boolean',
        'api_secret' => 'encrypted',
    ];
}
