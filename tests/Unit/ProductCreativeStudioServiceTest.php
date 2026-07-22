<?php

namespace Tests\Unit;

use App\Models\MpProduct;
use App\Services\Marketplace\ProductCreativeStudioService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ProductCreativeStudioServiceTest extends TestCase
{
    public function test_it_generates_and_stores_a_grounded_product_image(): void
    {
        config()->set('marketplace.features.product_ai_studio_enabled', true);
        config()->set('ai.media_api_key', 'media-test-key');
        config()->set('ai.image_model', 'gemini-3.1-flash-image');
        Storage::fake('public');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'inlineData' => [
                                'mimeType' => 'image/png',
                                'data' => base64_encode('valid-test-image'),
                            ],
                        ]],
                    ],
                ]],
            ]),
        ]);
        $product = new MpProduct([
            'user_id' => 91,
            'product_name' => 'Basic Tişört',
            'brand' => 'Zolm',
            'category_name' => 'Tişört',
            'color' => 'Kırmızı',
            'size' => 'M',
        ]);

        $result = app(ProductCreativeStudioService::class)->generateImage($product, 'Açık fonda katalog çekimi', '1:1');

        Storage::disk('public')->assertExists($result['path']);
        $this->assertSame('gemini-3.1-flash-image', $result['model']);
        $this->assertStringContainsString('Renk: Kırmızı', $result['prompt']);
        $this->assertStringContainsString('Açık fonda katalog çekimi', $result['prompt']);
        Http::assertSent(fn ($request): bool => $request->hasHeader('x-goog-api-key', 'media-test-key')
            && data_get($request->data(), 'generationConfig.imageConfig.aspectRatio') === '1:1');
    }

    public function test_it_refuses_generation_when_the_gradual_release_flag_is_closed(): void
    {
        config()->set('marketplace.features.product_ai_studio_enabled', false);
        config()->set('ai.media_api_key', 'media-test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('henüz bu ortamda aktif değil');

        app(ProductCreativeStudioService::class)->generateImage(new MpProduct(['user_id' => 1, 'product_name' => 'Ürün']));
    }

    public function test_it_generates_a_video_with_only_a_user_scoped_local_reference_image(): void
    {
        config()->set('marketplace.features.product_ai_video_enabled', true);
        config()->set('ai.media_api_key', 'media-test-key');
        config()->set('ai.video_model', 'gemini-omni-flash-preview');
        Storage::fake('public');
        Storage::disk('public')->put('mp-products/generated/91/reference.png', 'reference-image');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'steps' => [[
                    'type' => 'model_output',
                    'content' => [[
                        'type' => 'video',
                        'mime_type' => 'video/mp4',
                        'data' => base64_encode('valid-test-video'),
                    ]],
                ]],
            ]),
        ]);
        $product = new MpProduct([
            'user_id' => 91,
            'product_name' => 'Basic Tişört',
            'brand' => 'Zolm',
            'color' => 'Kırmızı',
        ]);

        $result = app(ProductCreativeStudioService::class)->generateVideo(
            $product,
            'Yavaş dönüşlü ürün videosu',
            '9:16',
            'mp-products/generated/91/reference.png',
        );

        Storage::disk('public')->assertExists($result['path']);
        $this->assertTrue($result['used_reference_image']);
        $this->assertSame('video/mp4', $result['mime_type']);
        Http::assertSent(fn ($request): bool => data_get($request->data(), 'model') === 'gemini-omni-flash-preview'
            && data_get($request->data(), 'input.0.type') === 'image'
            && data_get($request->data(), 'response_format.aspect_ratio') === '9:16');
    }
}
