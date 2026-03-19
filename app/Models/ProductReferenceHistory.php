<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReferenceHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'stok_kodu',
        'change_source',
        'note',
        'previous_snapshot',
        'new_snapshot',
        'changed_by',
    ];

    protected $casts = [
        'previous_snapshot' => 'array',
        'new_snapshot' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
