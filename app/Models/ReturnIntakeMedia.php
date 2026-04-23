<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ReturnIntakeMedia extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'optimized_at' => 'datetime',
            'storage_meta' => 'array',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ReturnIntakeItem::class, 'return_intake_item_id');
    }

    public function publicUrl(): ?string
    {
        try {
            return Storage::disk($this->disk)->url($this->path);
        } catch (\Throwable) {
            return null;
        }
    }

    public function thumbnailUrl(): ?string
    {
        if (!$this->thumbnail_path) {
            return $this->publicUrl();
        }

        try {
            return Storage::disk($this->disk)->url($this->thumbnail_path);
        } catch (\Throwable) {
            return $this->publicUrl();
        }
    }
}
