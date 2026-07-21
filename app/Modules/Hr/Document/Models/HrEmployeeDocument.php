<?php

namespace App\Modules\Hr\Document\Models;

use App\Models\HrFile;
use App\Models\LegalEntity;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Document\Enums\DocumentStatus;
use App\Modules\Hr\Document\Enums\VerificationStatus;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrEmployeeDocument extends Model
{
    use BelongsToLegalEntity, SoftDeletes;

    protected $fillable = [
        'legal_entity_id', 'employee_id', 'document_type_id', 'current_file_id',
        'document_number_encrypted', 'document_number_hash', 'document_number_last_four',
        'issue_date', 'expiry_date', 'status', 'verification_status',
        'verified_by', 'verified_at', 'rejection_reason', 'notes', 'version_number',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'document_number_encrypted' => 'encrypted',
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'status' => DocumentStatus::class,
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(HrDocumentType::class);
    }

    public function currentFile(): BelongsTo
    {
        return $this->belongsTo(HrFile::class, 'current_file_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(HrEmployeeDocumentVersion::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', DocumentStatus::Active);
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('status', DocumentStatus::Active)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($days))
            ->where('expiry_date', '>=', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('status', DocumentStatus::Active)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now());
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expiry_date) {
            return null;
        }

        return (int) now()->diffInDays($this->expiry_date, false);
    }
}
