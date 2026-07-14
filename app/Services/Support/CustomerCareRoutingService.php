<?php

namespace App\Services\Support;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportRoutingRule;
use App\Models\SupportAgentAction;
use App\Models\SlaTrack;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerCareRoutingService
{
    /**
     * Konuşmayı kural motoruna göre yönlendirir.
     */
    public function route(SupportConversation $conversation): ?int
    {
        $rules = SupportRoutingRule::where('store_id', $conversation->store_id)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        foreach ($rules as $rule) {
            if ($this->ruleMatches($conversation, $rule)) {
                $conversation->update(['support_team_id' => $rule->support_team_id]);

                // Audit log
                SupportAgentAction::create([
                    'conversation_id' => $conversation->id,
                    'action' => 'conversation_routed',
                    'details_json' => [
                        'rule_id' => $rule->id,
                        'trigger_type' => $rule->trigger_type,
                        'support_team_id' => $rule->support_team_id,
                    ]
                ]);

                return $rule->support_team_id;
            }
        }
        return null;
    }

    /**
     * Konuşmayı temsilci olarak sahiplenir ( claim - optimistic lock ).
     * P1-3 FIX: Servis seviyesinde tenant/store actor guard.
     */
    public function claim(SupportConversation $conversation, User $user): bool
    {
        // P1-3: Actor kendi store'una ait bir konuşmayı claim edebilir.
        // system actor (id=1 veya role=admin) bypass edilebilir (CLI/background path).
        if (!$this->actorCanAccessStore($user, $conversation->store_id)) {
            Log::warning('CustomerCareRoutingService::claim tenant guard blocked', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'conversation_store_id' => $conversation->store_id,
            ]);
            return false;
        }

        return DB::transaction(function () use ($conversation, $user) {
            $fresh = SupportConversation::where('id', $conversation->id)
                ->where(function ($q) use ($user) {
                    $q->whereNull('assigned_user_id')
                      ->orWhere('assigned_user_id', $user->id);
                })
                ->lockForUpdate()
                ->first();

            if (!$fresh) {
                return false;
            }

            $fresh->update([
                'assigned_user_id' => $user->id,
                'ownership_status' => 'human',
            ]);

            SupportAgentAction::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'action' => 'claim',
                'details_json' => ['assigned_user_id' => $user->id]
            ]);

            return true;
        });
    }

    /**
     * Konuşma sahipliğini AI'a geri bırakır.
     * P1-3 FIX: Servis seviyesinde tenant/store actor guard.
     */
    public function release(SupportConversation $conversation, User $user): bool
    {
        // P1-3: Actor kendi store'una ait bir konuşmayı release edebilir.
        if (!$this->actorCanAccessStore($user, $conversation->store_id)) {
            Log::warning('CustomerCareRoutingService::release tenant guard blocked', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'conversation_store_id' => $conversation->store_id,
            ]);
            return false;
        }

        return DB::transaction(function () use ($conversation, $user) {
            $fresh = SupportConversation::where('id', $conversation->id)
                ->where('assigned_user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$fresh) {
                return false;
            }

            $fresh->update([
                'assigned_user_id' => null,
                'ownership_status' => 'ai',
            ]);

            SupportAgentAction::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'action' => 'release',
                'details_json' => ['released_by' => $user->id]
            ]);

            return true;
        });
    }

    /**
     * SLA İhlallerini denetler ve eskalasyon kurallarını tetikler.
     */
    public function checkSlaEscalations(int $storeId): int
    {
        $activeTracks = SlaTrack::where('store_id', $storeId)
            ->where('status', 'active')
            ->with('conversation')
            ->get();

        $escalatedCount = 0;
        foreach ($activeTracks as $track) {
            $conv = $track->conversation;
            if (!$conv) continue;

            $now = Carbon::now();
            $breached = false;
            $breachType = '';

            if (!$track->first_response_breached && $now->gt($track->first_response_deadline) && is_null($track->first_response_at)) {
                $track->first_response_breached = true;
                $breached = true;
                $breachType = 'first_response';
            }

            if (!$track->resolution_breached && $now->gt($track->resolution_deadline) && is_null($track->resolved_at)) {
                $track->resolution_breached = true;
                $breached = true;
                $breachType = 'resolution';
            }

            if ($breached) {
                $track->save();

                // Eskale durumuna getir (Öncelik Yüksek)
                $conv->update([
                    'priority' => 'high',
                ]);

                // Audit log yaz
                SupportAgentAction::create([
                    'conversation_id' => $conv->id,
                    'action' => 'sla_escalated',
                    'details_json' => [
                        'sla_track_id' => $track->id,
                        'breach_type' => $breachType,
                        'deadline' => $breachType === 'first_response' ? $track->first_response_deadline->toDateTimeString() : $track->resolution_deadline->toDateTimeString(),
                    ]
                ]);

                $escalatedCount++;
            }
        }

        return $escalatedCount;
    }

    /**
     * Kuralın eşleşip eşleşmediğini doğrular.
     */
    protected function ruleMatches(SupportConversation $conversation, SupportRoutingRule $rule): bool
    {
        switch ($rule->trigger_type) {
            case 'channel':
                return $conversation->source_type === $rule->trigger_value;

            case 'rating':
                $ref = $conversation->source_reference_json ?? [];
                $rating = (int)($ref['rating'] ?? 5);
                // Örn: trigger_value = "<=2" veya "2"
                if (str_starts_with($rule->trigger_value, '<=')) {
                    $val = (int)substr($rule->trigger_value, 2);
                    return $rating <= $val;
                }
                return $rating === (int)$rule->trigger_value;

            case 'intent':
                $lastMsg = SupportMessage::where('conversation_id', $conversation->id)
                    ->where('sender_type', 'customer')
                    ->orderBy('id', 'desc')
                    ->first();
                if (!$lastMsg) return false;

                $body = mb_strtolower($lastMsg->body_encrypted);
                $keywords = array_filter(explode(',', mb_strtolower($rule->trigger_value)));
                foreach ($keywords as $kw) {
                    if (str_contains($body, trim($kw))) {
                        return true;
                    }
                }
                return false;

            case 'cart_value':
                $ref = $conversation->source_reference_json ?? [];
                $cartVal = (float)($ref['cart_value'] ?? 0);
                return $cartVal >= (float)$rule->trigger_value;

            case 'business_hours':
                // Basitçe mesai saatleri dışı (09:00 - 18:00 dışı veya haftasonu)
                $now = Carbon::now();
                $isWeekend = $now->isWeekend();
                $isOutsideHours = $now->hour < 9 || $now->hour >= 18;
                return $isWeekend || $isOutsideHours;
        }

        return false;
    }

    /**
     * P1-3: Actor'ın belirtilen store'a erişim yetkisini kontrol eder.
     * - admin/system aktör her store'a erişebilir (CLI/background path).
     * - Normal kullanıcı yalnız kendi store_id'lerini claim/release edebilir.
     */
    protected function actorCanAccessStore(User $user, ?int $storeId): bool
    {
        if (!$storeId) {
            return false;
        }

        // System/admin bypass (CLI/background path)
        if (in_array($user->role ?? '', ['admin', 'superadmin'], true)) {
            return true;
        }

        // Normal kullanıcı: kendi store'larına erişebilir
        return \App\Models\MarketplaceStore::where('user_id', $user->id)
            ->where('id', $storeId)
            ->exists();
    }
}
