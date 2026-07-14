<?php

use App\Http\Controllers\WhatsAppReturnWebhookController;
use App\Http\Controllers\MarketplaceWebhookController;
use App\Http\Controllers\WhatsApp\WebhookController;
use App\Http\Controllers\WhatsApp\BoosterWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('/customer-care/widget/{publicKey}')->group(function () {
    Route::options('/{path?}', [\App\Http\Controllers\CustomerCare\WebChatWidgetController::class, 'preflight'])
        ->where('path', '.*');
    Route::get('/configuration', [\App\Http\Controllers\CustomerCare\WebChatWidgetController::class, 'configuration']);
    Route::post('/session', [\App\Http\Controllers\CustomerCare\WebChatWidgetController::class, 'bootstrap']);
    Route::post('/messages', [\App\Http\Controllers\CustomerCare\WebChatWidgetController::class, 'sendMessage']);
    Route::post('/attachments', [\App\Http\Controllers\CustomerCare\WebChatWidgetController::class, 'uploadAttachment']);
    Route::post('/handoff', [\App\Http\Controllers\CustomerCare\WebChatWidgetController::class, 'requestHandoff']);
    Route::get('/messages', [\App\Http\Controllers\CustomerCare\WebChatWidgetController::class, 'poll']);
    Route::post('/ack', [\App\Http\Controllers\CustomerCare\WebChatWidgetController::class, 'acknowledge']);
});

Route::post('/webhooks/marketplaces/{provider}/{store}', [MarketplaceWebhookController::class, 'handle'])
    ->name('marketplace.webhooks.receive');

Route::get('/customer-care/webhooks/meta/{store}', [\App\Http\Controllers\CustomerCare\InboundChannelWebhookController::class, 'verifyMeta']);
Route::post('/customer-care/webhooks/{provider}/{store}', [\App\Http\Controllers\CustomerCare\InboundChannelWebhookController::class, 'receive'])
    ->where('provider', 'meta|instagram|facebook|google_business|crm|erp');

Route::get('/webhooks/returns/whatsapp', [WhatsAppReturnWebhookController::class, 'verify'])
    ->name('returns.whatsapp.verify');

Route::post('/webhooks/returns/whatsapp', [WhatsAppReturnWebhookController::class, 'receive'])
    ->name('returns.whatsapp.receive');

// ── WhatsApp Webhook Endpoint'leri ────────────────────────────────
// Meta webhook: feature flag nedeniyle 404 VERMEZ (güvenlik kuralı)
Route::get('/whatsapp/webhook', [WebhookController::class, 'verify'])
    ->name('whatsapp.webhook.verify');

Route::post('/whatsapp/webhook', [WebhookController::class, 'handleMeta'])
    ->name('whatsapp.webhook.receive');

// ZOLM Booster webhook
Route::post('/whatsapp/booster/event', [BoosterWebhookController::class, 'handleEvent'])
    ->name('whatsapp.booster.event');

// ── Enterprise API (Waves AR / AS) ────────────────────────────────
Route::prefix('/customer-care/v1')->middleware('throttle:customer-care-enterprise')->group(function () {
    Route::get('/conversations', [\App\Http\Controllers\CustomerCare\EnterpriseApiController::class, 'getConversations'])
        ->name('customer-care.api.conversations');

    Route::get('/conversations/{id}/messages', [\App\Http\Controllers\CustomerCare\EnterpriseApiController::class, 'getMessages'])
        ->name('customer-care.api.messages');

    Route::post('/conversations/{id}/reply', [\App\Http\Controllers\CustomerCare\EnterpriseApiController::class, 'reply'])
        ->name('customer-care.api.reply');

    Route::get('/analytics/summary', [\App\Http\Controllers\CustomerCare\EnterpriseApiController::class, 'getAnalyticsSummary'])
        ->name('customer-care.api.analytics');
});
