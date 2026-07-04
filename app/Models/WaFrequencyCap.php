<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaFrequencyCap extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id', 'store_id', 'message_class',
        'rolling_window_key', 'sent_count', 'last_sent_at',
    ];

    protected function casts(): array
    {
        return ['last_sent_at' => 'datetime'];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WaContact::class, 'contact_id');
    }
}
