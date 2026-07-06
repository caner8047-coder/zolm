<?php

namespace App\Policies;

use App\Models\AdCampaign;
use App\Models\User;

class AdCampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessAds();
    }

    public function view(User $user, AdCampaign $campaign): bool
    {
        return $user->canAccessAds() && $campaign->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return in_array($user->roleSlug(), ['admin', 'manager', 'uretim_sorumlusu']);
    }

    public function update(User $user, AdCampaign $campaign): bool
    {
        return $user->canAccessAds() && $campaign->user_id === $user->id;
    }

    public function delete(User $user, AdCampaign $campaign): bool
    {
        return $user->isAdmin() && $campaign->user_id === $user->id;
    }

    public function import(User $user): bool
    {
        return in_array($user->roleSlug(), ['admin', 'manager', 'uretim_sorumlusu']);
    }
}
