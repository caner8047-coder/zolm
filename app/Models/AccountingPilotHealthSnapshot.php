<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPilotHealthSnapshot extends Model
{
    protected $table = 'accounting_pilot_health_snapshots';

    protected $fillable = [
        'user_id',
        'run_uuid',
        'status',
        'score',
        'failed_count',
        'warning_count',
        'checks_json',
        'meta_json',
    ];

    protected $casts = [
        'checks_json' => 'array',
        'meta_json' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isWarning(): bool
    {
        return $this->status === 'warning';
    }
}
