<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ReportFile extends Model
{
    protected $fillable = [
        'report_id',
        'filename',
        'file_path',
        'sheet_type',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function getFullPathAttribute(): string
    {
        return Storage::disk('local')->path($this->file_path);
    }

    public function exists(): bool
    {
        return Storage::disk('local')->exists($this->file_path);
    }
}
