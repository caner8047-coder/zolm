<?php

namespace App\Modules\Hr\Expense\Models;

use App\Models\HrFile;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Expense\Enums\ExpenseStatus;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrExpense extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = ['legal_entity_id', 'employee_id', 'expense_category_id', 'receipt_file_id', 'expense_date', 'currency', 'net_amount', 'vat_rate', 'vat_amount', 'gross_amount', 'status', 'merchant_name', 'document_number', 'description', 'project_reference', 'order_reference', 'customer_reference', 'source_key', 'payload_hash', 'requested_by', 'decided_by', 'decided_at', 'decision_note', 'paid_by', 'paid_at', 'payment_reference', 'finance_reference'];

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'net_amount' => 'decimal:2', 'vat_rate' => 'decimal:2', 'vat_amount' => 'decimal:2', 'gross_amount' => 'decimal:2', 'status' => ExpenseStatus::class, 'decided_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function employee(): BelongsTo { return $this->belongsTo(HrEmployee::class); }
    public function category(): BelongsTo { return $this->belongsTo(HrExpenseCategory::class, 'expense_category_id'); }
    public function receipt(): BelongsTo { return $this->belongsTo(HrFile::class, 'receipt_file_id'); }
    public function statusHistory(): HasMany { return $this->hasMany(HrExpenseStatusHistory::class, 'expense_id')->orderBy('created_at'); }
}
