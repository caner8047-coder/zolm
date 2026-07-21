<?php

namespace App\Modules\Hr\Safety\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrHealthRecord extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = [
        'legal_entity_id', 'employee_id', 'record_type', 'recorded_on', 'expires_on',
        'provider_encrypted', 'result_encrypted', 'details_encrypted', 'created_by',
    ];

    protected $hidden = ['provider_encrypted', 'result_encrypted', 'details_encrypted'];

    protected function casts(): array
    {
        return [
            'recorded_on' => 'date',
            'expires_on' => 'date',
            'provider_encrypted' => 'encrypted',
            'result_encrypted' => 'encrypted',
            'details_encrypted' => 'encrypted',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class);
    }
}
