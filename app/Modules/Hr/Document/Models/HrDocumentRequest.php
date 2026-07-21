<?php

namespace App\Modules\Hr\Document\Models;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrDocumentRequest extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = [
        'legal_entity_id', 'employee_id', 'document_type_id', 'requested_by',
        'due_date', 'message', 'status', 'fulfilled_document_id',
        'completed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function fulfilledDocument(): BelongsTo
    {
        return $this->belongsTo(HrEmployeeDocument::class, 'fulfilled_document_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());
    }
}
