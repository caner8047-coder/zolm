<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaControlGroupMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id', 'contact_id', 'store_id', 'status',
        'enrolled_at', 'excluded_at',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'excluded_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(WaControlGroup::class, 'group_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WaContact::class, 'contact_id');
    }
}
