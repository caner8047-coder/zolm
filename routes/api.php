<?php

use App\Http\Controllers\MarketplaceWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/marketplaces/{provider}/{store}', [MarketplaceWebhookController::class, 'handle'])
    ->name('marketplace.webhooks.receive');
