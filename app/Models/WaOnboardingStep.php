<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaOnboardingStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'flow_id', 'step_index', 'name', 'delay_type', 'delay_value',
        'template_key', 'template_params', 'coupon_key', 'status',
        'scheduled_at', 'sent_at', 'outbox_id',
    ];

    protected function casts(): array
    {
        return [
            'template_params' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(WaOnboardingFlow::class, 'flow_id');
    }

    public function outbox(): BelongsTo
    {
        return $this->belongsTo(WaOutbox::class, 'outbox_id');
    }
}
