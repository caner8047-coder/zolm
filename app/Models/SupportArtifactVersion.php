<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportArtifactVersion extends Model
{
    protected $fillable = [
        'store_id', 'artifact_type', 'artifact_id', 'version_number', 'content_json', 'is_current',
        'release_package_id',
    ];

    protected $casts = [
        'content_json' => 'array',
        'is_current' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function releasePackage(): BelongsTo
    {
        return $this->belongsTo(SupportReleasePackage::class, 'release_package_id');
    }
}
