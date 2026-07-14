<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportReleaseEvent extends Model
{
    protected $fillable = [
        'package_id', 'event_type', 'details_json',
    ];

    protected $casts = [
        'details_json' => 'array',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(SupportReleasePackage::class, 'package_id');
    }
}
