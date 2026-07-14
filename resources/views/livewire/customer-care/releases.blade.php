<div class="space-y-6 p-4 lg:p-6 bg-slate-50/50 min-h-screen">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-900">Knowledge & Instruction Releases</h1>
            <p class="text-sm text-slate-500">Yapay zeka prompt şablonları, marka ses tanımları ve bilgi bankası makalelerinin kontrollü yayın döngüsü.</p>
        </div>
        <div class="w-full sm:w-auto">
            <select wire:model.live="selectedStoreId" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:outline-none">
                @foreach($stores as $st)
                    <option value="{{ $st->id }}">{{ $st->store_name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Feedback Messages --}}
    @if($errorMessage)
        <div class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-[8px] text-sm">
            {{ $errorMessage }}
        </div>
    @endif
    @if($successMessage)
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-[8px] text-sm">
            {{ $successMessage }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Create Package Draft (Left Side) --}}
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Yeni Taslak Paket Oluştur</h2>
                <form wire:submit.prevent="createPackage" class="space-y-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-slate-700">Paket Başlığı</label>
                        <input type="text" wire:model="title" placeholder="Prompt Güncellemesi v1.2" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-base sm:text-sm">
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-slate-700">Talimat Tipi</label>
                        <select wire:model="artifactType" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-base sm:text-sm">
                            <option value="prompt_template">Prompt Şablonu (System Instructions)</option>
                            <option value="knowledge_article">Bilgi Bankası Makalesi</option>
                            <option value="brand_voice">Marka Sesi Tanımı</option>
                            <option value="policy_rule">Kanal Politikası</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-slate-700">Talimat İçeriği (JSON veya Düz Metin)</label>
                        <textarea wire:model="contentRaw" rows="6" placeholder='{"instruction": "Kibar davran ve link verme."}' class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 text-base sm:text-sm font-mono"></textarea>
                    </div>

                    <button type="submit" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 hover:bg-slate-800 text-white font-medium rounded-[6px] text-sm transition">
                        Paketi Taslak Olarak Kaydet
                    </button>
                </form>
            </div>

            {{-- Preflight Checklist Results Display --}}
            @if($activePackageId && !empty($preflightResults))
                <div class="bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm space-y-4">
                    <h2 class="text-lg font-semibold text-slate-900">Preflight Tarama Sonuçları (#{{ $activePackageId }})</h2>
                    <div class="space-y-3">
                        @foreach($preflightResults as $checkKey => $check)
                            <div class="p-3 rounded-[8px] border border-slate-100 bg-slate-50/50 space-y-1">
                                <div class="flex justify-between items-center text-xs">
                                    <span class="font-semibold text-slate-800">{{ $check['label'] }}</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase {{ $check['status'] === 'passed' ? 'bg-emerald-100 text-emerald-800' : ($check['status'] === 'not_applicable' ? 'bg-slate-100 text-slate-600' : 'bg-red-100 text-red-800') }}">
                                        {{ $check['status'] }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500">{{ $check['detail'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Packages Timeline & Diff View (Right Side) --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Yayın Paketleri Lifecycle</h2>
                <div class="overflow-x-auto">
                    <table class="w-full table-layout-fixed text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 text-xs font-semibold text-slate-400">
                                <th class="py-2.5">Paket Başlığı</th>
                                <th>Tip / Değişiklik</th>
                                <th>Durum</th>
                                <th>Oluşturan / Onay</th>
                                <th class="text-right">Aksiyonlar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($packages as $pkg)
                                <tr class="border-b border-slate-100 text-xs text-slate-700">
                                    <td class="py-3 font-semibold">
                                        #{{ $pkg->id }} - {{ $pkg->title }}
                                        <p class="text-[10px] text-slate-400">Oluşturulma: {{ $pkg->created_at->diffForHumans() }}</p>
                                    </td>
                                    <td>
                                        @foreach($pkg->items as $item)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-slate-100 text-slate-700 uppercase">
                                                {{ $item->artifact_type }}
                                            </span>
                                            <p class="text-[10px] text-slate-500 truncate font-mono max-w-[150px]">
                                                {{ json_encode($item->new_content_json) }}
                                            </p>
                                        @endforeach
                                    </td>
                                    <td>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase {{ $pkg->status === 'published' ? 'bg-emerald-50 text-emerald-700' : ($pkg->status === 'rejected' || $pkg->status === 'rolled_back' ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-700') }}">
                                            {{ $pkg->status }}
                                        </span>
                                        @if($pkg->published_at)
                                            <p class="text-[9px] text-slate-400">{{ $pkg->published_at->diffForHumans() }}</p>
                                        @endif
                                    </td>
                                    <td>
                                        <p class="font-medium">{{ $pkg->creator->name ?? 'System' }}</p>
                                        @if($pkg->approver)
                                            <p class="text-[9px] text-slate-400">Onay: {{ $pkg->approver->name }}</p>
                                        @endif
                                    </td>
                                    <td class="text-right space-y-1">
                                        @if($pkg->status === 'draft')
                                            <button wire:click="runPreflight({{ $pkg->id }})" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-[6px] text-[10px] transition">
                                                Preflight Çalıştır
                                            </button>
                                        @elseif($pkg->status === 'review' || $pkg->status === 'approved')
                                            <button wire:click="publishPackage({{ $pkg->id }})" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-[6px] text-[10px] transition">
                                                Yayınla (Publish)
                                            </button>
                                        @elseif($pkg->status === 'published')
                                            <button wire:click="rollbackPackage({{ $pkg->id }})" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-red-600 hover:bg-red-500 text-white rounded-[6px] text-[10px] transition">
                                                Rollback Et
                                            </button>
                                        @else
                                            <span class="text-slate-400 text-[10px]">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-center text-slate-400">Herhangi bir yayın paketi bulunamadı.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
