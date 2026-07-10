<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EDocumentEvent extends Model
{
    protected $table = 'e_document_events';
    protected $guarded = ['id'];

    public function eDocument(): BelongsTo
    {
        return $this->belongsTo(EDocument::class);
    }
}
