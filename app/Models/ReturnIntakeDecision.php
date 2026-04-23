<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnIntakeDecision extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'marketplace_pushed_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ReturnIntakeItem::class, 'return_intake_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
