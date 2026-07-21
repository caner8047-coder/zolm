<?php

namespace App\Modules\Hr\Document\Models;

use App\Models\LegalEntity;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Document\Enums\DocumentCategory;
use App\Modules\Hr\Document\Enums\DocumentSensitivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrDocumentType extends Model
{
    use BelongsToLegalEntity, HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Hr\DocumentTypeFactory::new();
    }

    protected $fillable = [
        'legal_entity_id', 'code', 'name', 'category', 'description', 'sensitivity',
        'requires_expiry_date', 'requires_issue_date', 'requires_document_number',
        'allowed_mime_types', 'max_file_size_kb', 'default_validity_months',
        'is_mandatory', 'employee_can_upload', 'is_active', 'sort_order',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => DocumentCategory::class,
            'sensitivity' => DocumentSensitivity::class,
            'requires_expiry_date' => 'boolean',
            'requires_issue_date' => 'boolean',
            'requires_document_number' => 'boolean',
            'allowed_mime_types' => 'array',
            'is_mandatory' => 'boolean',
            'employee_can_upload' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(HrDocumentRequirement::class);
    }

    public function employeeDocuments(): HasMany
    {
        return $this->hasMany(HrEmployeeDocument::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
