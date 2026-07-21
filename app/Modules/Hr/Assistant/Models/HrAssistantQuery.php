<?php

namespace App\Modules\Hr\Assistant\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use Illuminate\Database\Eloquent\Model;

class HrAssistantQuery extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = [
        'legal_entity_id', 'user_id', 'query_encrypted', 'intent', 'status',
        'response_encrypted', 'sources', 'answered_at',
    ];

    protected $hidden = ['query_encrypted', 'response_encrypted'];

    protected function casts(): array
    {
        return [
            'query_encrypted' => 'encrypted',
            'response_encrypted' => 'encrypted',
            'sources' => 'array',
            'answered_at' => 'datetime',
        ];
    }

    public function queryText(): string
    {
        return (string) $this->query_encrypted;
    }

    public function responseText(): string
    {
        return (string) $this->response_encrypted;
    }
}
