<?php

use App\Http\Controllers\WhatsAppReturnWebhookController;
use App\Http\Controllers\MarketplaceWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/marketplaces/{provider}/{store}', [MarketplaceWebhookController::class, 'handle'])
    ->name('marketplace.webhooks.receive');

Route::get('/webhooks/returns/whatsapp', [WhatsAppReturnWebhookController::class, 'verify'])
    ->name('returns.whatsapp.verify');

Route::post('/webhooks/returns/whatsapp', [WhatsAppReturnWebhookController::class, 'receive'])
    ->name('returns.whatsapp.receive');
