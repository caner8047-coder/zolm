<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportPilotBaseline extends Model
{
    protected $fillable = [
        'store_id', 'period_start', 'period_end', 'sample_size',
        'average_human_handle_seconds', 'approved_by_user_id', 'approved_at',
    ];
    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date',
            'average_human_handle_seconds' => 'float', 'approved_at' => 'datetime',
        ];
    }
}
