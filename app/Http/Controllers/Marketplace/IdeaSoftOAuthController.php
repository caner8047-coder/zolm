<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\IdeaSoftOAuthService;
use App\Services\Marketplace\MarketplaceStoreAccessResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IdeaSoftOAuthController extends Controller
{
    public function redirect(
        Request $request,
        MarketplaceStore $store,
        MarketplaceStoreAccessResolver $resolver,
        IdeaSoftOAuthService $oauth,
    ): RedirectResponse {
        $resolvedStore = $resolver->resolveForView($request->user(), (int) $store->id);
        abort_unless($resolvedStore->marketplace === 'ideasoft', 404);

        $state = Str::random(64);
        $request->session()->put('marketplace.ideasoft.oauth.'.$state, [
            'store_id' => $resolvedStore->id,
            'user_id' => $request->user()->id,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        return redirect()->away($oauth->authorizationUrl($resolvedStore, $oauth->redirectUri(), $state));
    }

    public function callback(
        Request $request,
        MarketplaceStoreAccessResolver $resolver,
        IdeaSoftOAuthService $oauth,
    ): RedirectResponse {
        $state = trim((string) $request->query('state'));
        $sessionKey = 'marketplace.ideasoft.oauth.'.$state;
        $oauthState = preg_match('/^[A-Za-z0-9]{64}$/', $state) === 1
            ? $request->session()->pull($sessionKey)
            : null;

        if (! is_array($oauthState)
            || (int) ($oauthState['user_id'] ?? 0) !== (int) $request->user()->id
            || (int) ($oauthState['expires_at'] ?? 0) < now()->timestamp) {
            return redirect()->route('mp.integrations', ['ideasoft_oauth' => 'invalid_state'])
                ->with('error', 'IdeaSoft yetkilendirme oturumu geçersiz veya süresi dolmuş.');
        }

        $storeId = (int) ($oauthState['store_id'] ?? 0);
        $store = $resolver->resolveForView($request->user(), $storeId);

        if (filled($request->query('error'))) {
            $message = trim((string) ($request->query('error_description') ?: $request->query('error')));
            ActivityLog::log('ideasoft_oauth_denied', 'IdeaSoft OAuth yetkilendirmesi reddedildi.', 'MarketplaceStore', $store->id, [
                'error' => (string) $request->query('error'),
            ]);

            return redirect()->route('mp.integrations', ['store' => $store->id, 'ideasoft_oauth' => 'denied'])
                ->with('error', 'IdeaSoft yetkilendirmesi tamamlanmadı: '.$message);
        }

        $code = trim((string) $request->query('code'));

        if ($code === '') {
            return redirect()->route('mp.integrations', ['store' => $store->id, 'ideasoft_oauth' => 'missing_code'])
                ->with('error', 'IdeaSoft yetkilendirme kodu dönmedi.');
        }

        try {
            $payload = $oauth->exchangeAuthorizationCode($store, $code, $oauth->redirectUri());
            ActivityLog::log('ideasoft_oauth_completed', 'IdeaSoft OAuth yetkilendirmesi tamamlandı.', 'MarketplaceStore', $store->id, [
                'scope' => data_get($payload, 'scope'),
                'token_type' => data_get($payload, 'token_type'),
            ]);

            return redirect()->route('mp.integrations', ['store' => $store->id, 'ideasoft_oauth' => 'success'])
                ->with('success', 'IdeaSoft mağazası başarıyla yetkilendirildi.');
        } catch (\Throwable $exception) {
            $store->connection?->forceFill([
                'status' => 'error',
                'last_error' => $exception->getMessage(),
            ])->save();
            ActivityLog::log('ideasoft_oauth_failed', 'IdeaSoft OAuth token değişimi başarısız oldu.', 'MarketplaceStore', $store->id, [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('mp.integrations', ['store' => $store->id, 'ideasoft_oauth' => 'failed'])
                ->with('error', 'IdeaSoft token alınamadı. Bilgileri ve Redirect URI ayarını kontrol edip tekrar deneyin.');
        }
    }
}
