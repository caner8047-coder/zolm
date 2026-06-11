<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialPriceHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id',
        'user_id',
        'old_price',
        'new_price',
        'reason',
    ];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
