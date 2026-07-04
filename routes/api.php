<?php

use App\Http\Controllers\WhatsAppReturnWebhookController;
use App\Http\Controllers\MarketplaceWebhookController;
use App\Http\Controllers\WhatsApp\WebhookController;
use App\Http\Controllers\WhatsApp\BoosterWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/marketplaces/{provider}/{store}', [MarketplaceWebhookController::class, 'handle'])
    ->name('marketplace.webhooks.receive');

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
