@php
    $creativeStudioEnabled = (bool) config('marketplace.features.product_ai_studio_enabled', false);
    $creativeVideoEnabled = (bool) config('marketplace.features.product_ai_video_enabled', false);
    $creativeStudioConfigured = trim((string) config('ai.media_api_key', '')) !== '';
@endphp

<section class="rounded-[10px] border border-slate-200 bg-white shadow-sm" data-testid="mp-products-ai-studio">
    <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h4 class="text-sm font-semibold text-slate-900">Ürüne bağlı AI Studio</h4>
                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-600">Görsel</span>
                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-500">{{ config('ai.image_model') }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-500">Ürün adı, marka, kategori ve varyant bilgilerini otomatik bağlama alır; üretilen dosya önce önizlemeye gelir.</p>
        </div>
        <span class="inline-flex shrink-0 rounded-[6px] px-2 py-1 text-xs font-medium {{ $creativeStudioEnabled && $creativeStudioConfigured ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
            {{ $creativeStudioEnabled && $creativeStudioConfigured ? 'Kullanıma hazır' : 'Yapılandırma bekliyor' }}
        </span>
    </div>

    <div class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_17rem]">
        <div class="min-w-0 space-y-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Kreatif yönerge</label>
                <textarea wire:model="creativeStudioInstruction"
                          rows="3"
                          maxlength="600"
                          class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:py-2 sm:text-sm"
                          placeholder="Örn. Açık fonda, ürünü merkezde gösteren temiz katalog çekimi"></textarea>
                @error('creativeStudioInstruction') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="sm:w-44">
                    <label class="mb-1 block text-xs font-medium text-slate-700">Görsel oranı</label>
                    <select wire:model="creativeStudioAspectRatio" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:py-2 sm:text-sm">
                        <option value="1:1">1:1 · Kare</option>
                        <option value="3:4">3:4 · Dikey ürün</option>
                        <option value="4:3">4:3 · Yatay ürün</option>
                        <option value="9:16">9:16 · Hikâye</option>
                        <option value="16:9">16:9 · Yatay banner</option>
                    </select>
                </div>
                <button type="button"
                        wire:click="generateProductCreativeImage"
                        wire:loading.attr="disabled"
                        wire:target="generateProductCreativeImage"
                        @disabled(!$creativeStudioEnabled || !$creativeStudioConfigured)
                        class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40 sm:w-auto sm:py-2">
                    <span wire:loading.remove wire:target="generateProductCreativeImage">Ürün görseli üret</span>
                    <span wire:loading wire:target="generateProductCreativeImage">Görsel üretiliyor…</span>
                </button>
            </div>

            @if(!$creativeStudioEnabled)
                <p class="text-xs text-amber-700">Kademeli yayın bayrağı kapalı. <code class="font-mono">MARKETPLACE_PRODUCT_AI_STUDIO_ENABLED=true</code> ile açılır.</p>
            @elseif(!$creativeStudioConfigured)
                <p class="text-xs text-amber-700">Medya anahtarı eksik. <code class="font-mono">AI_MEDIA_API_KEY</code> yapılandırılmalı.</p>
            @endif

            @if($creativeStudioFeedback !== '')
                <div class="rounded-[8px] border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800" role="status">{{ $creativeStudioFeedback }}</div>
            @endif
        </div>

        <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
            @if(data_get($creativeStudioImage, 'url'))
                <img src="{{ data_get($creativeStudioImage, 'url') }}" alt="AI Studio önizleme" class="aspect-square w-full rounded-[6px] border border-slate-200 bg-white object-contain">
                <div class="mt-3 flex flex-col gap-2 sm:flex-row lg:flex-col">
                    <button type="button" wire:click="applyCreativeStudioImage" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-3 py-3 text-sm font-medium text-white hover:bg-slate-800 sm:py-2">Ana görsele uygula</button>
                    <button type="button" wire:click="clearCreativeStudioImage" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 sm:py-2">Önizlemeyi kaldır</button>
                </div>
            @else
                <div class="flex aspect-square items-center justify-center rounded-[6px] border border-dashed border-slate-300 bg-white p-5 text-center">
                    <div>
                        <svg class="mx-auto h-7 w-7 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="mt-2 text-xs text-slate-500">Üretilen görsel burada önizlenir.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="border-t border-slate-200 p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h5 class="text-sm font-semibold text-slate-900">Tek tık ürün videosu</h5>
                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-500">{{ config('ai.video_model') }}</span>
                </div>
                <p class="mt-1 text-xs leading-5 text-slate-500">Önce görsel üretirseniz video o görseli ürün referansı olarak kullanır; aksi halde ürün kartındaki doğrulanmış bilgilerle çalışır.</p>
            </div>
            <span class="inline-flex shrink-0 rounded-[6px] px-2 py-1 text-xs font-medium {{ $creativeVideoEnabled && $creativeStudioConfigured ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                {{ $creativeVideoEnabled && $creativeStudioConfigured ? 'Video hazır' : 'Pilot kapalı' }}
            </span>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_17rem]">
            <div class="min-w-0 space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Video yönergesi</label>
                    <textarea wire:model="creativeStudioVideoInstruction" rows="3" maxlength="600" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:py-2 sm:text-sm" placeholder="Örn. Ürünü yavaş dönüşle gösteren sade, tek sahneli ürün videosu"></textarea>
                    @error('creativeStudioVideoInstruction') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="sm:w-44">
                        <label class="mb-1 block text-xs font-medium text-slate-700">Video oranı</label>
                        <select wire:model="creativeStudioVideoAspectRatio" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:py-2 sm:text-sm">
                            <option value="9:16">9:16 · Dikey</option>
                            <option value="16:9">16:9 · Yatay</option>
                        </select>
                    </div>
                    <button type="button" wire:click="generateProductCreativeVideo" wire:loading.attr="disabled" wire:target="generateProductCreativeVideo" @disabled(!$creativeVideoEnabled || !$creativeStudioConfigured) class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40 sm:w-auto sm:py-2">
                        <span wire:loading.remove wire:target="generateProductCreativeVideo">Ürün videosu üret</span>
                        <span wire:loading wire:target="generateProductCreativeVideo">Video üretiliyor…</span>
                    </button>
                </div>
                @if(!$creativeVideoEnabled)
                    <p class="text-xs text-amber-700">Maliyetli video üretimi ayrı pilot bayrağıyla açılır: <code class="font-mono">MARKETPLACE_PRODUCT_AI_VIDEO_ENABLED=true</code>.</p>
                @endif
                @if($creativeStudioVideoFeedback !== '')
                    <div class="rounded-[8px] border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800" role="status">{{ $creativeStudioVideoFeedback }}</div>
                @endif
            </div>

            <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                @if(data_get($creativeStudioVideo, 'url'))
                    <video controls playsinline class="aspect-[9/16] max-h-72 w-full rounded-[6px] border border-slate-200 bg-slate-950 object-contain">
                        <source src="{{ data_get($creativeStudioVideo, 'url') }}" type="video/mp4">
                    </video>
                    <div class="mt-3 flex flex-col gap-2">
                        <button type="button" wire:click="applyCreativeStudioVideo" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-3 py-3 text-sm font-medium text-white hover:bg-slate-800 sm:py-2">Videoyu ürüne ekle</button>
                        <button type="button" wire:click="clearCreativeStudioVideo" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 sm:py-2">Önizlemeyi kaldır</button>
                    </div>
                @else
                    <div class="flex aspect-video items-center justify-center rounded-[6px] border border-dashed border-slate-300 bg-white p-5 text-center">
                        <p class="text-xs text-slate-500">Üretilen MP4 burada önizlenir.</p>
                    </div>
                @endif
            </div>
        </div>

        @if($f_video_urls)
            <div class="mt-4 border-t border-slate-200 pt-4">
                <h6 class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Ürüne eklenecek videolar</h6>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($f_video_urls as $videoIndex => $videoUrl)
                        <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <video controls preload="metadata" class="aspect-video w-full rounded-[6px] bg-slate-950 object-contain"><source src="{{ $videoUrl }}" type="video/mp4"></video>
                            <button type="button" wire:click="removeProductVideoUrl({{ $videoIndex }})" class="mt-2 inline-flex min-h-[36px] w-full items-center justify-center rounded-[6px] border border-rose-200 bg-white px-3 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50">Listeden kaldır</button>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="border-t border-slate-200 bg-slate-50/60 px-4 py-3 text-xs leading-5 text-slate-500">
        AI çıktısı doğrudan yayına alınmaz. Görsel veya videoyu uygulamak yalnız düzenleme formunu değiştirir; kalıcı kayıt için ayrıca “Güncelle” gerekir.
    </div>
</section>
