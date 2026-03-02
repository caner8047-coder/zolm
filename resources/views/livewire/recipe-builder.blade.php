<div>
    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-red-800 text-sm">{{ session('error') }}</div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('recipe.materials') }}" class="text-gray-400 hover:text-gray-900">← Geri</a>
                <h1 class="text-2xl font-bold text-gray-900">{{ $recipeId ? 'Reçete Düzenle' : 'Yeni Reçete' }}</h1>
                @if($status !== 'draft')
                    <span class="px-2 py-0.5 text-xs rounded-full {{ $status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">
                        {{ \App\Models\Recipe::STATUSES[$status] ?? $status }}
                    </span>
                @endif
            </div>
        </div>
        <div class="flex gap-2">
            <button wire:click="saveRecipe" class="px-4 py-2.5 text-sm font-medium bg-gray-900 text-white rounded-lg hover:bg-gray-800">
                💾 Kaydet
            </button>
            @if($recipeId)
            <button wire:click="activateRecipe" class="px-4 py-2.5 text-sm font-medium bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">
                ✅ Aktif Et
            </button>
            <button wire:click="duplicateRecipe" class="px-4 py-2.5 text-sm border rounded-lg hover:bg-gray-50">
                📋 Kopyala
            </button>
            @endif
        </div>
    </div>

    {{-- Reçete Bilgisi Panel --}}
    <div class="bg-white rounded-xl border p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Ürün Seçimi --}}
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Nihai Ürün (Pazaryeri)</label>
                <div class="relative">
                    <input wire:model.live.debounce.300ms="productSearch" type="text"
                           placeholder="Ürün adı veya stok kodu ile arayın..."
                           class="w-full px-3 py-2.5 text-sm border rounded-lg {{ $selectedProductId ? 'bg-emerald-50 border-emerald-300' : '' }}">
                    @if($selectedProductId)
                        <button wire:click="clearProduct" class="absolute right-2 top-2.5 text-gray-400 hover:text-red-500 text-sm">✕</button>
                    @endif
                    @if(count($productResults) > 0)
                    <div class="absolute z-30 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-48 overflow-y-auto">
                        @foreach($productResults as $pr)
                        <button wire:click="selectProduct({{ $pr['id'] }})"
                                class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 border-b last:border-0">
                            <span class="font-medium">{{ $pr['product_name'] }}</span>
                            <span class="text-xs text-gray-400 ml-2">{{ $pr['stock_code'] ?? '' }}</span>
                        </button>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Reçete Adı *</label>
                <input wire:model="recipeName" type="text" class="w-full px-3 py-2.5 text-sm border rounded-lg" placeholder="Ürün reçetesi">
                @error('recipeName') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Versiyon</label>
                <input wire:model="version" type="text" class="w-full px-3 py-2.5 text-sm border rounded-lg" placeholder="v1">
            </div>
        </div>
    </div>

    {{-- Görünüm Modları --}}
    <div class="flex gap-2 mb-4 border-b border-gray-200">
        <button wire:click="$set('viewMode', 'all')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors {{ $viewMode === 'all' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            Tüm Satırlar ({{ count($lines) }})
        </button>
        @foreach(\App\Models\RecipeLine::OPERATIONS as $opKey => $opLabel)
            @php $opCount = collect($lines)->where('operation', $opKey)->count(); @endphp
            @if($opCount > 0)
            <button wire:click="$set('viewMode', 'all')"
                    class="px-3 py-2.5 text-xs font-medium border-b-2 border-transparent text-gray-400">
                {{ $opLabel }} ({{ $opCount }})
            </button>
            @endif
        @endforeach
        <button wire:click="$set('viewMode', 'bom')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors {{ $viewMode === 'bom' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            📊 Konsolide BOM
        </button>
    </div>

    @if($viewMode === 'bom' && $recipeId)
    {{-- ═══════════════ KONSOLİDE BOM ═══════════════ --}}
    <div class="bg-white rounded-xl border overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Stok Kodu</th>
                    <th class="px-4 py-3 text-left">Malzeme</th>
                    <th class="px-4 py-3 text-center">Toplam Miktar</th>
                    <th class="px-4 py-3 text-center">Birim</th>
                    <th class="px-4 py-3 text-left">Operasyonlar</th>
                    <th class="px-4 py-3 text-right">Maliyet</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($this->consolidatedBom as $bom)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs">{{ $bom['material_code'] }}</td>
                    <td class="px-4 py-3 font-medium">{{ $bom['material_name'] }}</td>
                    <td class="px-4 py-3 text-center font-bold">{{ number_format($bom['total_qty'], 4) }}</td>
                    <td class="px-4 py-3 text-center text-gray-500">{{ \App\Models\Material::UNITS[$bom['unit']] ?? $bom['unit'] }}</td>
                    <td class="px-4 py-3">
                        @foreach($bom['operations'] as $op)
                            <span class="px-1.5 py-0.5 text-xs bg-gray-100 rounded mr-1">{{ \App\Models\RecipeLine::OPERATIONS[$op] ?? $op }}</span>
                        @endforeach
                    </td>
                    <td class="px-4 py-3 text-right">
                        @if($bom['unit_price'])
                            {{ number_format($bom['total_cost'], 2) }} ₺
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    {{-- ═══════════════ REÇETE SATIRLARI ═══════════════ --}}
    <div class="bg-white rounded-xl border">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-3 py-3 text-left w-24">Operasyon</th>
                        <th class="px-3 py-3 text-left w-28">Kullanılan Yer</th>
                        <th class="px-3 py-3 text-left" style="min-width:200px">Malzeme</th>
                        <th class="px-3 py-3 text-left w-28">Hesap Tipi</th>
                        <th class="px-3 py-3 text-center w-16">En</th>
                        <th class="px-3 py-3 text-center w-16">Boy</th>
                        <th class="px-3 py-3 text-center w-16">Yük.</th>
                        <th class="px-3 py-3 text-center w-16">Adet</th>
                        <th class="px-3 py-3 text-center w-16">Fire</th>
                        <th class="px-3 py-3 text-center w-24">Sonuç</th>
                        <th class="px-3 py-3 text-center w-20">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($lines as $i => $line)
                    <tr class="hover:bg-gray-50 group" wire:key="line-{{ $i }}">
                        {{-- Operasyon --}}
                        <td class="px-3 py-2">
                            <select wire:model.live="lines.{{ $i }}.operation" class="w-full px-2 py-1.5 text-xs border rounded">
                                @foreach(\App\Models\RecipeLine::OPERATIONS as $k => $v)
                                    <option value="{{ $k }}">{{ $v }}</option>
                                @endforeach
                            </select>
                        </td>
                        {{-- Kullanılan Yer --}}
                        <td class="px-3 py-2">
                            <input wire:model.lazy="lines.{{ $i }}.usage_area" type="text"
                                   class="w-full px-2 py-1.5 text-xs border rounded" placeholder="oturum, sırt...">
                        </td>
                        {{-- Malzeme --}}
                        <td class="px-3 py-2">
                            @if($line['material_id'])
                                <div class="flex items-center gap-1">
                                    <span class="text-xs truncate flex-1" title="{{ $line['material_label'] }}">{{ $line['material_label'] }}</span>
                                    <button wire:click="$set('lines.{{ $i }}.material_id', null)" class="text-gray-400 hover:text-red-500 text-xs flex-shrink-0">✕</button>
                                </div>
                            @else
                                <div class="relative">
                                    <input wire:keyup.debounce.300ms="searchMaterial({{ $i }}, $event.target.value)" type="text"
                                           class="w-full px-2 py-1.5 text-xs border rounded" placeholder="Malzeme ara...">
                                    @if($searchingLineIndex === $i && count($materialSearchResults) > 0)
                                    <div class="absolute z-30 w-64 mt-1 bg-white border rounded-lg shadow-lg max-h-40 overflow-y-auto">
                                        @foreach($materialSearchResults as $mr)
                                        <button wire:click="selectMaterial({{ $i }}, {{ $mr['id'] }})"
                                                class="w-full text-left px-3 py-2 text-xs hover:bg-gray-50 border-b last:border-0">
                                            <span class="font-mono text-gray-500">{{ $mr['code'] }}</span>
                                            <span class="ml-1">{{ \Illuminate\Support\Str::limit($mr['name'], 30) }}</span>
                                        </button>
                                        @endforeach
                                        <button wire:click="openQuickMaterialModal({{ $i }})"
                                                class="w-full text-left px-3 py-2 text-xs text-blue-600 hover:bg-blue-50 font-medium">
                                            + Yeni Malzeme Oluştur
                                        </button>
                                    </div>
                                    @endif
                                </div>
                            @endif
                        </td>
                        {{-- Hesap Tipi --}}
                        <td class="px-3 py-2">
                            <select wire:model.live="lines.{{ $i }}.calc_type" class="w-full px-2 py-1.5 text-xs border rounded">
                                @foreach(\App\Models\RecipeLine::CALC_TYPES as $k => $v)
                                    <option value="{{ $k }}">{{ $v }}</option>
                                @endforeach
                            </select>
                        </td>
                        {{-- Ölçüler --}}
                        @if($line['calc_type'] === 'fixed_qty')
                        <td class="px-3 py-2" colspan="4">
                            <input wire:model.live.debounce.500ms="lines.{{ $i }}.constant_qty" type="number" step="0.01"
                                   class="w-24 px-2 py-1.5 text-xs border rounded" placeholder="Miktar">
                        </td>
                        @else
                        <td class="px-3 py-2">
                            <input wire:model.live.debounce.500ms="lines.{{ $i }}.width_cm" type="number" step="0.1"
                                   class="w-full px-2 py-1.5 text-xs border rounded text-center" placeholder="cm">
                        </td>
                        <td class="px-3 py-2">
                            <input wire:model.live.debounce.500ms="lines.{{ $i }}.length_cm" type="number" step="0.1"
                                   class="w-full px-2 py-1.5 text-xs border rounded text-center" placeholder="cm">
                        </td>
                        <td class="px-3 py-2">
                            @if($line['calc_type'] === 'volume_m3')
                            <input wire:model.live.debounce.500ms="lines.{{ $i }}.height_cm" type="number" step="0.1"
                                   class="w-full px-2 py-1.5 text-xs border rounded text-center" placeholder="cm">
                            @else
                            <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <input wire:model.live.debounce.500ms="lines.{{ $i }}.pieces" type="number" step="0.01" min="0"
                                   class="w-full px-2 py-1.5 text-xs border rounded text-center" placeholder="1">
                        </td>
                        @endif
                        {{-- Fire Override --}}
                        <td class="px-3 py-2">
                            @if($line['calc_type'] !== 'fixed_qty')
                            <input wire:model.live.debounce.500ms="lines.{{ $i }}.waste_rate_override" type="number" step="0.01" min="0" max="1"
                                   class="w-full px-2 py-1.5 text-xs border rounded text-center" placeholder="auto">
                            @endif
                        </td>
                        {{-- Sonuç --}}
                        <td class="px-3 py-2 text-center">
                            <div class="font-bold text-gray-900 text-xs" title="Hesaplanan miktar">
                                {{ number_format($line['calculated_qty'] ?? 0, 4) }}
                            </div>
                            <div class="text-[10px] text-gray-400">
                                {{ \App\Models\Material::UNITS[$line['calculated_unit'] ?? 'pcs'] ?? $line['calculated_unit'] ?? '' }}
                            </div>
                            @if(isset($calculationResults[$i]))
                            <div class="text-[9px] text-blue-400 cursor-help" title="{{ json_encode($calculationResults[$i]) }}">
                                📐 debug
                            </div>
                            @endif
                        </td>
                        {{-- İşlem --}}
                        <td class="px-3 py-2 text-center">
                            <div class="flex justify-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button wire:click="duplicateLine({{ $i }})" class="p-1 text-gray-400 hover:text-blue-600" title="Kopyala">📋</button>
                                <button wire:click="moveLine({{ $i }}, 'up')" class="p-1 text-gray-400 hover:text-gray-900" title="Yukarı">↑</button>
                                <button wire:click="moveLine({{ $i }}, 'down')" class="p-1 text-gray-400 hover:text-gray-900" title="Aşağı">↓</button>
                                <button wire:click="removeLine({{ $i }})" class="p-1 text-gray-400 hover:text-red-600" title="Sil">🗑️</button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" class="px-4 py-12 text-center text-gray-400">
                            <div class="text-4xl mb-2">📝</div>
                            <p>Henüz satır eklenmedi.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Alt Bar --}}
        <div class="px-4 py-3 border-t bg-gray-50 flex items-center justify-between">
            <button wire:click="addLine" class="px-4 py-2 text-sm font-medium bg-white border rounded-lg hover:bg-gray-50">
                + Satır Ekle
            </button>
            <div class="flex items-center gap-4 text-xs text-gray-500">
                <span>{{ count($lines) }} satır</span>
                @if(count($lines) > 0)
                <button wire:click="recalculateAll" class="text-blue-600 hover:underline">🔄 Tümünü Hesapla</button>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════════ HIZLI MALZEME MODAL ═══════════════ --}}
    @if($showQuickMaterialModal)
    <div class="fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showQuickMaterialModal', false)">
        <div class="bg-white rounded-2xl w-full max-w-md">
            <div class="p-6 border-b">
                <h2 class="text-lg font-bold">⚡ Hızlı Malzeme Oluştur</h2>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Stok Kodu *</label>
                        <input wire:model="qmCode" type="text" class="w-full px-3 py-2 text-sm border rounded-lg">
                        @error('qmCode') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Kategori</label>
                        <select wire:model.live="qmCategory" class="w-full px-3 py-2 text-sm border rounded-lg">
                            @foreach(\App\Models\Material::CATEGORIES as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Malzeme Adı *</label>
                    <input wire:model="qmName" type="text" class="w-full px-3 py-2 text-sm border rounded-lg">
                    @error('qmName') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Birim</label>
                        <select wire:model="qmUnit" class="w-full px-3 py-2 text-sm border rounded-lg">
                            @foreach(\App\Models\Material::UNITS as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Fire</label>
                        <input wire:model="qmWasteRate" type="number" step="0.01" min="0" max="1"
                               class="w-full px-3 py-2 text-sm border rounded-lg">
                    </div>
                </div>
                @if($qmCategory === 'fabric')
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Kumaş Eni (cm)</label>
                    <input wire:model="qmFabricWidth" type="number" class="w-full px-3 py-2 text-sm border rounded-lg" placeholder="140">
                </div>
                @endif
            </div>
            <div class="p-6 border-t flex justify-end gap-3">
                <button wire:click="$set('showQuickMaterialModal', false)" class="px-4 py-2 text-sm border rounded-lg hover:bg-gray-50">İptal</button>
                <button wire:click="saveQuickMaterial" class="px-4 py-2 text-sm bg-gray-900 text-white rounded-lg hover:bg-gray-800">Kaydet & Ata</button>
            </div>
        </div>
    </div>
    @endif
</div>
