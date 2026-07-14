<?php

namespace App\Services\Support;

use App\Models\SupportAgentPresence;
use App\Models\SupportSavedView;
use App\Models\SupportReplyMacro;
use App\Models\SupportReplyMacroVersion;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Access\AuthorizationException;

class CustomerCareWorkspaceService
{
    /**
     * Temsilcinin konuşmadaki aktif varlığını (presence) kaydeder.
     */
    public function registerPresence(int $conversationId, int $userId): void
    {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        // Konuşma erişim kontrolü (Tenant)
        TenantContext::enforceConversationAccess($conversationId, $user);

        // Eski varlıkları temizle (TTL: 60 saniye)
        SupportAgentPresence::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->delete();

        SupportAgentPresence::create([
            'conversation_id' => $conversationId,
            'user_id'         => $userId,
            'last_active_at'  => now(),
        ]);

        // Genel TTL temizliği (60 saniye öncesi)
        SupportAgentPresence::where('last_active_at', '<', now()->subSeconds(60))->delete();
    }

    /**
     * Konuşmayı görüntüleyen diğer aktif temsilcileri döner.
     */
    public function getActivePresences(int $conversationId, int $excludeUserId): \Illuminate\Support\Collection
    {
        // Genel TTL temizliği
        SupportAgentPresence::where('last_active_at', '<', now()->subSeconds(60))->delete();

        return SupportAgentPresence::where('conversation_id', $conversationId)
            ->where('user_id', '!=', $excludeUserId)
            ->with('user')
            ->get();
    }

    /**
     * Kişiselleştirilmiş görünüm (saved view) kaydeder (store scoped).
     */
    public function saveSavedView(int $userId, int $storeId, string $name, array $filters): SupportSavedView
    {
        $user = User::findOrFail($userId);
        TenantContext::enforceStoreAccess($storeId, $user);

        return SupportSavedView::updateOrCreate(
            [
                'user_id'  => $userId,
                'store_id' => $storeId,
                'name'     => $name,
            ],
            [
                'filters_json' => $filters,
            ]
        );
    }

    /**
     * Temsilcinin yetkili olduğu mağazadaki görünüm filtrelerini döner.
     */
    public function getSavedViews(int $userId, int $storeId): \Illuminate\Support\Collection
    {
        $user = User::findOrFail($userId);
        TenantContext::enforceStoreAccess($storeId, $user);

        return SupportSavedView::where('user_id', $userId)
            ->where('store_id', $storeId)
            ->get();
    }

    /**
     * Makro içeriğindeki değişkenleri ({customer_name}, {store_name} vb.) yerine koyar.
     */
    public function renderMacro(SupportReplyMacro $macro, array $variables = [], ?User $user = null): string
    {
        $user = $user ?? Auth::user();
        if ($user) {
            TenantContext::enforceStoreAccess($macro->store_id, $user);
        }

        $body = $macro->body;

        foreach ($variables as $key => $val) {
            $body = str_replace("{{$key}}", $val, $body);
        }

        return $body;
    }
}
