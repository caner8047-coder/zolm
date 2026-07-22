<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MpCategory extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_leaf' => 'boolean',
            'level' => 'integer',
            'raw_payload' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }
}
