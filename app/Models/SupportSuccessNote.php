<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class SupportSuccessNote extends Model
{
    protected $fillable = [
        'store_id', 'author_id', 'body_encrypted',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function getBodyAttribute(): string
    {
        try {
            return Crypt::decryptString($this->body_encrypted);
        } catch (\Throwable) {
            return '[DECRYPT_HATASI]';
        }
    }

    /**
     * PII maskeleme: e-posta ve TCKN verilerini maskeler, ardından şifreler.
     */
    public static function createRedacted(int $storeId, int $authorId, string $rawBody): self
    {
        $masked = preg_replace(
            ['/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', '/\b\d{11}\b/'],
            ['[E-POSTA-MASKELENDİ]', '[TCKN-MASKELENDİ]'],
            $rawBody
        );

        return static::create([
            'store_id'       => $storeId,
            'author_id'      => $authorId,
            'body_encrypted' => Crypt::encryptString($masked),
        ]);
    }
}
