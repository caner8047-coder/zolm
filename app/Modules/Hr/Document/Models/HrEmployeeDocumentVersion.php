<?php

namespace App\Modules\Hr\Document\Models;

use App\Models\HrFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrEmployeeDocumentVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'employee_document_id', 'file_id', 'version_number', 'uploaded_by', 'change_reason',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function employeeDocument(): BelongsTo
    {
        return $this->belongsTo(HrEmployeeDocument::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(HrFile::class, 'file_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
