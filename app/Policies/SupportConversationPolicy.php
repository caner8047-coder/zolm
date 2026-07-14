<?php

namespace App\Policies;

use App\Models\User;
use App\Models\SupportConversation;
use App\Services\Support\TenantContext;

class SupportConversationPolicy
{
    public function view(User $user, SupportConversation $conversation): bool
    {
        return TenantContext::validateConversationAccess($conversation->id, $user);
    }

    public function claim(User $user, SupportConversation $conversation): bool
    {
        return TenantContext::validateConversationAccess($conversation->id, $user)
            && in_array($user->role, ['admin', 'manager', 'operator']);
    }

    public function release(User $user, SupportConversation $conversation): bool
    {
        return TenantContext::validateConversationAccess($conversation->id, $user)
            && in_array($user->role, ['admin', 'manager', 'operator']);
    }

    public function resolve(User $user, SupportConversation $conversation): bool
    {
        return TenantContext::validateConversationAccess($conversation->id, $user)
            && in_array($user->role, ['admin', 'manager', 'operator']);
    }
}
