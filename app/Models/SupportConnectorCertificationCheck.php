<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportConnectorCertificationCheck extends Model
{
    protected $fillable = [
        'run_id',
        'check_name',
        'status',
        'details',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(SupportConnectorCertificationRun::class, 'run_id');
    }
}
