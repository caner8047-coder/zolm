<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnDailyReport extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'totals_json' => 'array',
            'decision_breakdown_json' => 'array',
            'condition_breakdown_json' => 'array',
            'operator_breakdown_json' => 'array',
            'store_breakdown_json' => 'array',
            'auto_policy_json' => 'array',
            'hot_items_json' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
