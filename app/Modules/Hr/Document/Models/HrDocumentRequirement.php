<?php

namespace App\Modules\Hr\Document\Models;

use App\Models\LegalEntity;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrDocumentRequirement extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = [
        'legal_entity_id', 'document_type_id', 'branch_id', 'department_id',
        'position_id', 'employment_type', 'is_required', 'required_on_hire',
        'due_days_after_hire', 'reminder_days_before_expiry',
        'effective_from', 'effective_to',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'required_on_hire' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(HrDocumentType::class);
    }
}
