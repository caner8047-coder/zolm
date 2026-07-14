<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\Support\CustomerCareEnterpriseApiService;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportUsage;
use App\Services\Support\TenantContext;
use App\Services\Support\CustomerCareEntitlementService;

class EnterpriseApiController extends Controller
{
    protected CustomerCareEnterpriseApiService $apiService;

    public function __construct(CustomerCareEnterpriseApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    /**
     * Ortak yetkilendirme ve güvenlik kontrolü.
     */
    protected function authenticateAndCheck(Request $request, string $scope, int $storeId): \App\Models\SupportApiToken
    {
        $token = $this->authenticateRequest($request);
        $this->authorizeTokenForStore($token, $scope, $storeId);

        return $token;
    }

    /**
     * Kimlik doğrulamayı kaynak kaydı sorgulanmadan önce yapar. Böylece geçersiz
     * istemciler konuşma kimliklerinin varlığını 404/401 farkından çıkaramaz.
     */
    protected function authenticateRequest(Request $request): \App\Models\SupportApiToken
    {
        if (!config('customer-care.enabled', false)
            || !config('customer-care.enterprise_api_enabled', false)) {
            abort(404);
        }

        $plainToken = $request->bearerToken();
        if (!$plainToken) {
            abort(401, 'Yetkisiz erişim. Bearer Token gereklidir.');
        }

        $token = $this->apiService->authenticateToken($plainToken);
        if (!$token) {
            abort(401, 'Geçersiz veya süresi dolmuş API Token.');
        }

        return $token;
    }

    protected function authorizeTokenForStore(
        \App\Models\SupportApiToken $token,
        string $scope,
        int $storeId
    ): void {

        // Token tenant/scope sınırı entitlement sorgusundan önce doğrulanır; yabancı
        // mağazada ticari durum probe edilemez ve gereksiz entitlement logu oluşmaz.
        $this->apiService->checkAccess($token, $scope, $storeId);

        // Entitlement (Ticari Paket) kontrolü (Dalga AS)
        $entitlementService = app(CustomerCareEntitlementService::class);
        $clientOwner = $token->apiClient?->legalEntity?->user;
        if (!$clientOwner || !$entitlementService->hasEntitlement($storeId, 'enterprise_api', $clientOwner)) {
            abort(403, 'Bu mağazanın Enterprise API kullanım hakkı bulunmamaktadır.');
        }
    }

    /**
     * GET /api/customer-care/v1/conversations
     */
    public function getConversations(Request $request): JsonResponse
    {
        $storeId = (int) $request->query('store_id');
        if (!$storeId) {
            return response()->json(['error' => 'store_id query parametresi zorunludur.'], 400);
        }

        $token = $this->authenticateAndCheck($request, 'conversations:read', $storeId);
        $redactor = app(\App\Services\Support\Security\PiiRedactor::class);

        $conversations = SupportConversation::where('store_id', $storeId)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(function ($conv) use ($redactor) {
                return [
                    'id'                       => $conv->id,
                    'store_id'                 => $conv->store_id,
                    'support_channel_id'       => $conv->support_channel_id,
                    'external_conversation_id' => $conv->external_conversation_id,
                    'external_customer_id'     => $conv->external_customer_id
                        ? $redactor->maskPii((string) $conv->external_customer_id)
                        : null,
                    'source_type'              => $conv->source_type,
                    'status'                   => $conv->status,
                    'ai_mode'                  => $conv->ai_mode,
                    'version'                  => $conv->version,
                    'created_at'               => $conv->created_at,
                    'updated_at'               => $conv->updated_at,
                ];
            });

        $this->apiService->logAccess(
            $token->apiClient,
            $token,
            $storeId,
            'GET',
            $request->getRequestUri(),
            200,
            $request->ip(),
            $request->all()
        );

        return response()->json($conversations);
    }

    /**
     * GET /api/customer-care/v1/conversations/{id}/messages
     */
    public function getMessages(Request $request, $id): JsonResponse
    {
        $token = $this->authenticateRequest($request);
        $conv = SupportConversation::whereIn('store_id', array_map('intval', $token->store_ids ?? []))
            ->findOrFail($id);
        $this->authorizeTokenForStore($token, 'messages:read', (int) $conv->store_id);

        $redactor = app(\App\Services\Support\Security\PiiRedactor::class);

        // PII minimization: mesaj metinlerini decrypt edip döneriz ama hassas verileri filtreleyebiliriz.
        $messages = SupportMessage::where('conversation_id', $conv->id)
            ->orderBy('created_at')
            ->get()
            ->map(function ($msg) use ($redactor) {
                $body = (string) ($msg->body_encrypted ?? '');
                return [
                    'id'              => $msg->id,
                    'direction'       => $msg->direction,
                    'sender_type'     => $msg->sender_type,
                    'message_type'    => $msg->message_type,
                    'body'            => $redactor->maskPii($body),
                    'delivery_status' => $msg->delivery_status,
                    'sent_at'         => $msg->sent_at,
                ];
            });

        $this->apiService->logAccess(
            $token->apiClient,
            $token,
            $conv->store_id,
            'GET',
            $request->getRequestUri(),
            200,
            $request->ip(),
            $request->all()
        );

        return response()->json($messages);
    }

    /**
     * POST /api/customer-care/v1/conversations/{id}/reply
     */
    public function reply(Request $request, $id): JsonResponse
    {
        $token = $this->authenticateRequest($request);
        $conv = SupportConversation::whereIn('store_id', array_map('intval', $token->store_ids ?? []))
            ->findOrFail($id);
        $this->authorizeTokenForStore($token, 'replies:create', (int) $conv->store_id);

        $body = $request->input('body');
        if (empty(trim($body))) {
            return response()->json(['error' => 'Mesaj gövdesi (body) boş olamaz.'], 400);
        }

        try {
            $message = $this->apiService->sendApiReply($conv->id, $body, $token);

            $this->apiService->logAccess(
                $token->apiClient,
                $token,
                $conv->store_id,
                'POST',
                $request->getRequestUri(),
                200,
                $request->ip(),
                $request->all()
            );

            return response()->json([
                'success'    => true,
                'message_id' => $message->id,
                'status'     => 'pending',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->apiService->logAccess(
                $token->apiClient,
                $token,
                $conv->store_id,
                'POST',
                $request->getRequestUri(),
                403,
                $request->ip(),
                $request->all()
            );
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\Throwable $e) {
            $this->apiService->logAccess(
                $token->apiClient,
                $token,
                $conv->store_id,
                'POST',
                $request->getRequestUri(),
                422,
                $request->ip(),
                $request->all()
            );
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/customer-care/v1/analytics/summary
     */
    public function getAnalyticsSummary(Request $request): JsonResponse
    {
        $storeId = (int) $request->query('store_id');
        if (!$storeId) {
            return response()->json(['error' => 'store_id query parametresi zorunludur.'], 400);
        }

        $token = $this->authenticateAndCheck($request, 'analytics:read', $storeId);

        $usage = SupportUsage::where('store_id', $storeId)->first();

        $this->apiService->logAccess(
            $token->apiClient,
            $token,
            $storeId,
            'GET',
            $request->getRequestUri(),
            200,
            $request->ip(),
            $request->all()
        );

        return response()->json([
            'store_id'            => $storeId,
            'total_ai_drafts'     => $usage->total_ai_drafts ?? 0,
            'total_auto_replies'  => $usage->total_auto_replies ?? 0,
            'total_costs_usd'     => $usage->total_costs_usd ?? 0.0,
        ]);
    }
}
