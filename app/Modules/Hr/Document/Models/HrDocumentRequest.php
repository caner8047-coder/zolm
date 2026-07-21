<?php

namespace App\Modules\Hr\Document\Models;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Document\Events\EmployeeDocumentRequested;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class HrDocumentRequest extends Model
{
    use BelongsToLegalEntity, HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Hr\DocumentRequestFactory::new();
    }

    protected static function booted(): void
    {
        static::created(function (HrDocumentRequest $request) {
            // Event yalnızca transaction commit edildikten sonra yayınlanır.
            DB::afterCommit(function () use ($request) {
                event(new EmployeeDocumentRequested(
                    legalEntityId: $request->legal_entity_id,
                    documentRequestId: $request->id,
                    employeeId: $request->employee_id,
                    requestedByUserId: $request->requested_by,
                ));
            });
        });
    }

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
