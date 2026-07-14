<?php

namespace App\Services\Support;

use App\Models\SupportConversation;
use App\Models\SupportAgentAction;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

class SupportConversationService
{
    public function claim(SupportConversation $conversation, User $user): bool
    {
        if (Gate::forUser($user)->denies('claim', $conversation)) {
            throw new AuthorizationException('Bu işlemi yapmaya yetkiniz yok.');
        }

        $success = $conversation->claim($user->id);
        if ($success) {
            SupportAgentAction::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'action' => 'claimed',
                'details_json' => ['assigned_user_id' => $user->id],
            ]);
        }
        return $success;
    }

    public function releaseToAi(SupportConversation $conversation, User $user): bool
    {
        if (Gate::forUser($user)->denies('release', $conversation)) {
            throw new AuthorizationException('Bu işlemi yapmaya yetkiniz yok.');
        }

        $success = $conversation->releaseToAi();
        if ($success) {
            SupportAgentAction::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'action' => 'released_to_ai',
                'details_json' => [],
            ]);
        }
        return $success;
    }

    public function markAsResolved(SupportConversation $conversation, User $user): bool
    {
        if (Gate::forUser($user)->denies('resolve', $conversation)) {
            throw new AuthorizationException('Bu işlemi yapmaya yetkiniz yok.');
        }

        $success = $conversation->markAsResolved();
        if ($success) {
            SupportAgentAction::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'action' => 'resolved',
                'details_json' => [],
            ]);
        }
        return $success;
    }

    public function reopen(SupportConversation $conversation, User $user): bool
    {
        if (Gate::forUser($user)->denies('view', $conversation)) {
            throw new AuthorizationException('Bu işlemi yapmaya yetkiniz yok.');
        }

        if ($conversation->status !== 'open') {
            $conversation->status = 'open';
            $conversation->save();

            SupportAgentAction::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'action' => 'reopened',
                'details_json' => [],
            ]);
            return true;
        }
        return false;
    }

    public function changeAiMode(SupportConversation $conversation, string $mode, User $user): bool
    {
        if (Gate::forUser($user)->denies('view', $conversation)) {
            throw new AuthorizationException('Bu işlemi yapmaya yetkiniz yok.');
        }

        if (!in_array($mode, ['manual', 'copilot', 'automatic'])) {
            throw new \InvalidArgumentException('Geçersiz otomasyon modu.');
        }

        app(\App\Services\Support\Security\SupportRbacService::class)
            ->enforcePermission($user, (int) $conversation->store_id, 'toggle_automation');

        $oldMode = $conversation->ai_mode ?: 'manual';

        if ($mode === 'automatic') {
            $gate = app(\App\Services\Support\AI\CustomerCareAutomationGate::class);
            $candidate = clone $conversation;
            $candidate->ai_mode = 'automatic';
            $result = $gate->canAutomate($candidate, 100);
            if (!$result['allowed']) {
                throw new \RuntimeException('Otomatik mod etkinleştirilemez: ' . $result['reason']);
            }
        }

        $conversation->ai_mode = $mode;
        $success = $conversation->save();

        if ($success) {
            SupportAgentAction::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'action' => 'ai_mode_changed',
                'details_json' => [
                    'old_ai_mode' => $oldMode,
                    'new_ai_mode' => $mode,
                    'reason' => request()?->input('reason', 'agent_workspace_change'),
                ],
            ]);
        }

        return $success;
    }
}
