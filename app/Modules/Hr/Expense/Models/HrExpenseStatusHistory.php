<?php

namespace App\Modules\Hr\Expense\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Expense\Enums\ExpenseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrExpenseStatusHistory extends Model
{
    use BelongsToLegalEntity;

    public $timestamps = false;
    protected $table = 'hr_expense_status_history';
    protected $fillable = ['legal_entity_id', 'expense_id', 'from_status', 'to_status', 'note', 'acted_by', 'created_at'];
    protected function casts(): array { return ['from_status' => ExpenseStatus::class, 'to_status' => ExpenseStatus::class, 'created_at' => 'datetime']; }
    public function expense(): BelongsTo { return $this->belongsTo(HrExpense::class, 'expense_id'); }
}
