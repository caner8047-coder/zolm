<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SupportTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'name', 'description',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'support_team_members', 'support_team_id', 'user_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(SupportConversation::class, 'support_team_id');
    }
}
