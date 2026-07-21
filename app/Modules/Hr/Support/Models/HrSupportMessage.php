<?php

namespace App\Modules\Hr\Support\Models;

use App\Models\User;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrSupportMessage extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = ['legal_entity_id', 'support_ticket_id', 'author_user_id', 'body_encrypted', 'is_internal'];

    protected $hidden = ['body_encrypted'];

    protected function casts(): array
    {
        return ['body_encrypted' => 'encrypted', 'is_internal' => 'boolean'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HrSupportTicket::class, 'support_ticket_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function body(): string
    {
        return (string) $this->body_encrypted;
    }
}
