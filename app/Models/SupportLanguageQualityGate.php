<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportLanguageQualityGate extends Model
{
    protected $fillable = [
        'store_id', 'language', 'dataset_version', 'sample_size', 'average_score',
        'source_accuracy', 'critical_error_count', 'passed', 'approved_by_user_id', 'evaluated_at',
    ];
    protected function casts(): array
    {
        return [
            'average_score' => 'float', 'source_accuracy' => 'float', 'passed' => 'boolean',
            'evaluated_at' => 'datetime',
        ];
    }
}
