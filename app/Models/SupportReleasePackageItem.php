<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportReleasePackageItem extends Model
{
    protected $fillable = [
        'package_id', 'artifact_type', 'artifact_id', 'action', 'diff_json', 'new_content_json',
    ];

    protected $casts = [
        'diff_json' => 'array',
        'new_content_json' => 'array',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(SupportReleasePackage::class, 'package_id');
    }
}
