<?php

namespace App\Modules\Hr\Support\Models;

use App\Models\User;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrSupportTicket extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = [
        'legal_entity_id', 'ticket_number', 'requester_employee_id', 'requester_user_id', 'category',
        'subject', 'description_encrypted', 'priority', 'status', 'assigned_to', 'resolved_at', 'closed_at',
    ];

    protected $hidden = ['description_encrypted'];

    protected function casts(): array
    {
        return [
            'description_encrypted' => 'encrypted',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'requester_employee_id');
    }

    public function requesterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(HrSupportMessage::class, 'support_ticket_id');
    }

    public function description(): string
    {
        return (string) $this->description_encrypted;
    }
}
