<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'sla_track_id', 'event_type', 'details_json',
    ];

    protected function casts(): array
    {
        return ['details_json' => 'array'];
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(SlaTrack::class, 'sla_track_id');
    }
}
