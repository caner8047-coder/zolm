<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaAutomationEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'automation_id', 'contact_id', 'related_order_id', 'related_cart_id',
        'stage', 'status', 'entered_at', 'next_run_at', 'completed_at', 'exit_reason',
    ];

    protected function casts(): array
    {
        return [
            'entered_at' => 'datetime',
            'next_run_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(WaAutomationDefinition::class, 'automation_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WaContact::class, 'contact_id');
    }
}
