<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\MarketplaceStore;
use App\Models\SupportConversation;
use App\Models\SupportChannel;
use App\Models\SupportMessage;
use App\Models\ChannelProduct;
use App\Models\ChannelListing;
use App\Models\User;
use App\Models\LegalEntity;
use App\Services\Support\CustomerCareSalesAssistService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CustomerCareSalesAssistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['customer-care.enabled' => true]);
        config(['customer-care.inbox_enabled' => true]);
        config(['customer-care.sales_copilot_enabled' => true]);
        config(['customer-care.cart_recovery_enabled' => true]);
    }

    // ---------- Ortak yardımcı ----------

    private function makeConversation(string $code = 'SA', string $sourceType = 'whatsapp'): array
    {
        $user = User::factory()->create();
        $le = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'LE ' . $code,
            'tax_number' => rand(1000000000, 9999999999),
            'is_active' => true,
        ]);
        $store = MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $le->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Store ' . $code,
            'store_code' => $code,
            'seller_id' => rand(1000, 9999),
            'status' => 'active',
            'is_active' => true,
        ]);
        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => strtolower($sourceType) . '_main',
            'channel_type' => $sourceType,
            'name' => $sourceType . ' Channel',
            'is_enabled' => true,
            'config_json' => [],
        ]);
        $conv = SupportConversation::create([
            'store_id' => $store->id,
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv-' . $code,
            'external_customer_id' => 'cust-' . $code,
            'source_type' => $sourceType,
            'status' => 'open',
            'priority' => 'normal',
            'ai_mode' => 'manual',
            'ownership_status' => 'ai',
        ]);
        return [$store, $conv];
    }

    private function addInboundMessage(SupportConversation $conv, string $body): void
    {
        SupportMessage::create([
            'conversation_id' => $conv->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'body_encrypted' => $body,
            'body_preview' => mb_substr($body, 0, 50),
            'delivery_status' => 'delivered',
        ]);
    }

    private function addFreshListing(MarketplaceStore $store, int $idx = 1): void
    {
        $prod = ChannelProduct::create([
            'store_id' => $store->id,
            'external_product_id' => "ext-{$idx}",
            'stock_code' => "KAZAK-0{$idx}",
            'barcode' => "86800000000{$idx}",
            'title' => "Kazak Sürümü {$idx}",
            'brand' => 'BrandX',
            'category_name' => 'Giyim',
            'vat_rate' => 10.0,
        ]);
        ChannelListing::create([
            'store_id' => $store->id,
            'channel_product_id' => $prod->id,
            'listing_id' => "list-{$idx}",
            'sale_price' => 299.90,
            'list_price' => 349.90,
            'currency' => 'TRY',
            'stock_quantity' => 10,
            'last_stock_sync_at' => now(),
            'last_price_sync_at' => now(),
        ]);
    }

    // ---------- P0-2: Stale price / stale stock testleri ----------

    public function test_stale_price_listing_not_recommended()
    {
        [$store, $conv] = $this->makeConversation('SP1');
        $this->addInboundMessage($conv, 'Bu ürün stoku kalmadı, başka var mı?');

        $prod = ChannelProduct::create([
            'store_id' => $store->id,
            'external_product_id' => 'ext-stale',
            'stock_code' => 'STALE-PRICE',
            'barcode' => '868000000099',
            'title' => 'Stale Fiyatlı Ürün',
            'brand' => 'BrandX',
            'category_name' => 'Giyim',
            'vat_rate' => 10.0,
        ]);
        ChannelListing::create([
            'store_id' => $store->id,
            'channel_product_id' => $prod->id,
            'listing_id' => 'list-stale',
            'sale_price' => 299.90,
            'list_price' => 349.90,
            'currency' => 'TRY',
            'stock_quantity' => 5,
            'last_stock_sync_at' => now(), // fresh stok
            'last_price_sync_at' => now()->subHours(25), // STALE fiyat
        ]);

        $service = app(CustomerCareSalesAssistService::class);
        $sugs = $service->generateSalesSuggestions($conv);

        // Stale fiyatlı ürün önerilmemeli
        $types = collect($sugs)->pluck('type')->toArray();
        $this->assertNotContains('out_of_stock_alternative', $types,
            'Stale fiyatlı ürün alternatif olarak önerilmemeli');
    }

    public function test_suggested_draft_does_not_contain_explicit_price()
    {
        [$store, $conv] = $this->makeConversation('SP2');
        $this->addInboundMessage($conv, 'Bu kazak stokta kalmadı mı?');
        $this->addFreshListing($store, 1);

        $service = app(CustomerCareSalesAssistService::class);
        $sugs = $service->generateSalesSuggestions($conv);

        foreach ($sugs as $sug) {
            if ($sug['type'] === 'out_of_stock_alternative') {
                // P0-2: Taslak "Fiyatı: XXX TRY" gibi net fiyat içermemeli
                $this->assertStringNotContainsString('Fiyatı:', $sug['suggested_draft'],
                    'Önerilen taslak net fiyat cümlesi içermemeli');
                $this->assertStringNotContainsString('TRY', $sug['suggested_draft'],
                    'Taslak TRY para birimi içermemeli');
            }
        }
    }

    public function test_zero_price_listing_is_excluded_from_suggestions()
    {
        [$store, $conv] = $this->makeConversation('SP3');
        $this->addInboundMessage($conv, 'Ürün stoku kalmadı, başka var mı?');

        $prod = ChannelProduct::create([
            'store_id' => $store->id,
            'external_product_id' => 'ext-zero',
            'stock_code' => 'ZERO-PRICE',
            'barcode' => '868000000098',
            'title' => 'Sıfır Fiyatlı Ürün',
            'brand' => 'BrandX',
            'category_name' => 'Giyim',
            'vat_rate' => 10.0,
        ]);
        ChannelListing::create([
            'store_id' => $store->id,
            'channel_product_id' => $prod->id,
            'listing_id' => 'list-zero',
            'sale_price' => 0, // geçersiz fiyat
            'list_price' => 0,
            'currency' => 'TRY',
            'stock_quantity' => 5,
            'last_stock_sync_at' => now(),
            'last_price_sync_at' => now(),
        ]);

        $service = app(CustomerCareSalesAssistService::class);
        $sugs = $service->generateSalesSuggestions($conv);

        $types = collect($sugs)->pluck('type')->toArray();
        $this->assertNotContains('out_of_stock_alternative', $types,
            'Sıfır fiyatlı ürün önerilmemeli');
    }

    // ---------- P0-3: Cart signal verification testleri ----------

    public function test_unverified_cart_signal_does_not_generate_cart_recovery()
    {
        // source_type=web_chat ama cart_signal_verified=false
        [$store, $conv] = $this->makeConversation('CR1', 'web_chat');
        $conv->update([
            'source_reference_json' => [
                'cart_value' => 500.0,
                'cart_items' => ['item1'],
                // cart_signal_verified yok / false
            ],
        ]);

        $service = app(CustomerCareSalesAssistService::class);
        $sugs = $service->generateSalesSuggestions($conv);

        $types = collect($sugs)->pluck('type')->toArray();
        $this->assertNotContains('cart_recovery', $types,
            'Doğrulanmamış cart signal ile sepet kurtarma önerisi üretilmemeli');
    }

    public function test_verified_fresh_cart_signal_generates_cart_recovery()
    {
        [$store, $conv] = $this->makeConversation('CR2', 'web_chat');
        $conv->update([
            'source_reference_json' => [
                'cart_value' => 750.0,
                'cart_items' => ['item1'],
                'cart_signal_verified' => true,
                'cart_signal_at' => now()->toIso8601String(), // fresh
            ],
        ]);

        $service = app(CustomerCareSalesAssistService::class);
        $sugs = $service->generateSalesSuggestions($conv);

        $types = collect($sugs)->pluck('type')->toArray();
        $this->assertContains('cart_recovery', $types,
            'Doğrulanmış + fresh cart signal ile sepet kurtarma önerisi üretilmeli');
    }

    public function test_stale_verified_cart_signal_does_not_generate_recovery()
    {
        [$store, $conv] = $this->makeConversation('CR3', 'web_chat');
        $conv->update([
            'source_reference_json' => [
                'cart_value' => 600.0,
                'cart_items' => ['item1'],
                'cart_signal_verified' => true,
                'cart_signal_at' => now()->subMinutes(90)->toIso8601String(), // 90 dk STALE
            ],
        ]);

        $service = app(CustomerCareSalesAssistService::class);
        $sugs = $service->generateSalesSuggestions($conv);

        $types = collect($sugs)->pluck('type')->toArray();
        $this->assertNotContains('cart_recovery', $types,
            'Stale doğrulanmış cart signal ile sepet kurtarma üretilmemeli');
    }

    public function test_cart_recovery_only_works_for_web_chat_source()
    {
        // whatsapp source_type ile cart_value + verified olsa bile çalışmamalı
        [$store, $conv] = $this->makeConversation('CR4', 'whatsapp');
        $conv->update([
            'source_reference_json' => [
                'cart_value' => 800.0,
                'cart_signal_verified' => true,
                'cart_signal_at' => now()->toIso8601String(),
            ],
        ]);

        $service = app(CustomerCareSalesAssistService::class);
        $sugs = $service->generateSalesSuggestions($conv);

        $types = collect($sugs)->pluck('type')->toArray();
        $this->assertNotContains('cart_recovery', $types,
            'WhatsApp kanalında cart recovery çalışmamalı; yalnız web_chat');
    }

    // ---------- Mevcut testler ----------

    public function test_sales_suggestions_are_limited_to_max_3_items()
    {
        [$store, $conv] = $this->makeConversation('SA_LMT');
        $this->addInboundMessage($conv, 'Bu kazağın stoku kalmadı mı acaba?');

        for ($i = 1; $i <= 5; $i++) {
            $this->addFreshListing($store, $i);
        }

        $service = app(CustomerCareSalesAssistService::class);
        $sugs = $service->generateSalesSuggestions($conv);
        $this->assertLessThanOrEqual(3, count($sugs));
    }

    public function test_proactive_sales_suggestions_blocked_for_public_channels()
    {
        [$store, $conv] = $this->makeConversation('SA_PUB', 'google_business');
        $this->addInboundMessage($conv, 'Tükendi yazıyor, başka ürününüz yok mu?');

        $service = app(CustomerCareSalesAssistService::class);
        $sugs = $service->generateSalesSuggestions($conv);
        $this->assertEmpty($sugs);
    }

    public function test_catalog_suggestions_pii_redacted_in_title()
    {
        [$store, $conv] = $this->makeConversation('SA_PII');
        $this->addInboundMessage($conv, '05554443322 model ürünün stoku bitti mi?');

        // Ürün başlığında PII
        $prod = ChannelProduct::create([
            'store_id' => $store->id,
            'external_product_id' => 'ext-pii',
            'stock_code' => 'PII-PROD',
            'barcode' => '868000000097',
            'title' => 'Ürün 05554443322 model',
            'brand' => 'BrandX',
            'category_name' => 'Giyim',
            'vat_rate' => 10.0,
        ]);
        ChannelListing::create([
            'store_id' => $store->id,
            'channel_product_id' => $prod->id,
            'listing_id' => 'list-pii',
            'sale_price' => 199.90,
            'list_price' => 249.90,
            'currency' => 'TRY',
            'stock_quantity' => 3,
            'last_stock_sync_at' => now(),
            'last_price_sync_at' => now(),
        ]);

        $service = app(CustomerCareSalesAssistService::class);
        $sugs = $service->generateSalesSuggestions($conv);

        foreach ($sugs as $sug) {
            if (isset($sug['title'])) {
                $this->assertStringNotContainsString('05554443322', $sug['title'],
                    'Öneri başlığı PII içermemeli');
            }
        }
    }

    public function test_compatibility_advice_requires_verified_model_metadata(): void
    {
        [$store, $conv] = $this->makeConversation('SA_COMP');
        $this->addInboundMessage($conv, 'iPhone 15 Pro modeline uyar mı, eskisi tükendi?');
        $this->addFreshListing($store, 1);
        ChannelProduct::where('store_id', $store->id)->firstOrFail()->update(['title' => 'iPhone 15 Pro Kılıf']);

        $service = app(CustomerCareSalesAssistService::class);
        $this->assertEmpty($service->generateSalesSuggestions($conv));

        $product = ChannelProduct::where('store_id', $store->id)->firstOrFail();
        $product->update(['raw_payload' => ['verified_compatible_models' => ['iphone 15 pro']]]);
        $suggestions = $service->generateSalesSuggestions($conv);
        $this->assertNotEmpty($suggestions);
        $this->assertStringContainsString('doğrulanmış', $suggestions[0]['recommendation_reason']);
    }
}
