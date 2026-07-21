<?php

namespace App\Modules\Hr\Expense\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrExpenseCategory extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = ['legal_entity_id', 'code', 'name', 'requires_receipt', 'default_vat_rate', 'approval_limit', 'is_active', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['requires_receipt' => 'boolean', 'default_vat_rate' => 'decimal:2', 'approval_limit' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(HrExpense::class, 'expense_category_id');
    }
}
