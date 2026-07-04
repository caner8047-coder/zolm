<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaKnowledgeArticleChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id', 'chunk_index', 'content', 'content_hash',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(WaKnowledgeArticle::class, 'article_id');
    }
}
