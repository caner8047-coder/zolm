<div class="px-4 lg:px-6 py-6 mx-auto max-w-7xl">
    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-sm" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-red-800 text-sm">{{ session('error') }}</div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('recipe.materials') }}" class="text-gray-400 hover:text-gray-900 transition-colors">← Geri</a>
                <h1 class="text-xl lg:text-2xl font-bold text-gray-900">{{ $recipeId ? 'Reçete Düzenle' : 'Yeni Reçete' }}</h1>
                @if($status !== 'draft')
                    <span class="px-2 py-0.5 text-xs rounded-full {{ $status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">
                        {{ \App\Models\Recipe::STATUSES[$status] ?? $status }}
                    </span>
                @endif
            </div>
        </div>
        <div class="flex gap-2">
            <button wire:click="saveRecipe" class="w-full sm:w-auto px-4 py-3 sm:py-2.5 text-sm font-medium bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors">
                💾 Kaydet
            </button>
            @if($recipeId)
            <button wire:click="activateRecipe" class="w-full sm:w-auto px-4 py-3 sm:py-2.5 text-sm font-medium bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors">
                ✅ Aktif Et & COGS Güncelle
            </button>
            <button wire:click="duplicateRecipe" class="px-4 py-3 sm:py-2.5 text-sm border rounded-lg hover:bg-gray-50 transition-colors">
                📋 Kopyala
            </button>
            @endif
        </div>
    </div>

    {{-- Reçete Bilgisi Panel --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4 lg:p-5 mb-6 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Ürün Seçimi --}}
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Nihai Ürün (Pazaryeri)</label>
                <div class="relative">
                    <input wire:model.live.debounce.300ms="productSearch" type="text"
                           placeholder="Ürün adı veya stok kodu ile arayın..."
                           class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg {{ $selectedProductId ? 'bg-emerald-50 border-emerald-300' : '' }}">
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
                <input wire:model="recipeName" type="text" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg" placeholder="Ürün reçetesi">
                @error('recipeName') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Versiyon</label>
                <input wire:model="version" type="text" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg" placeholder="v1">
            </div>
        </div>
    </div>

    {{-- Maliyet Özet Kartları --}}
    @if(count($lines) > 0)
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-4">
        <div class="bg-white rounded-xl border border-gray-200 p-3 lg:p-4 shadow-sm">
            <div class="flex justify-between items-start">
                <div>
                    <div class="text-[10px] text-gray-500 uppercase font-medium">Toplam Maliyet</div>
                    <div class="text-lg lg:text-xl font-bold text-gray-900 mt-0.5">{{ number_format($this->totalCost, 2, ',', '.') }} ₺</div>
                </div>
                <button wire:click="$set('showSimModal', true)" class="p-1.5 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors" title="What-If Simülatörü">
                    🔮
                </button>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3 lg:p-4 shadow-sm">
            <div class="text-[10px] text-gray-500 uppercase font-medium">Satır Sayısı</div>
            <div class="text-lg lg:text-xl font-bold text-indigo-600 mt-0.5">{{ count($lines) }}</div>
        </div>
        @foreach($this->operationCosts as $opKey => $opData)
        <div class="bg-white rounded-xl border border-gray-200 p-3 lg:p-4 shadow-sm">
            <div class="text-[10px] text-gray-500 uppercase font-medium">{{ $opData['label'] }}</div>
            <div class="text-sm lg:text-base font-bold text-gray-800 mt-0.5">{{ number_format($opData['cost'], 2, ',', '.') }} ₺</div>
            <div class="text-[10px] text-gray-400">{{ $opData['count'] }} satır</div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Görünüm Modları --}}
    <div class="flex gap-2 mb-4 border-b border-gray-200 overflow-x-auto">
        <button wire:click="$set('viewMode', 'all')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap {{ $viewMode === 'all' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            Tüm Satırlar ({{ count($lines) }})
        </button>
        @foreach(\App\Models\RecipeLine::OPERATIONS as $opKey => $opLabel)
            @php $opCount = collect($lines)->where('operation', $opKey)->count(); @endphp
            @if($opCount > 0)
            <button wire:click="$set('viewMode', '{{ $opKey }}')"
                    class="px-3 py-2.5 text-xs font-medium border-b-2 transition-colors whitespace-nowrap {{ $viewMode === $opKey ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-400 hover:text-gray-700' }}">
                {{ $opLabel }} ({{ $opCount }})
            </button>
            @endif
        @endforeach
        <button wire:click="$set('viewMode', 'bom')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap {{ $viewMode === 'bom' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            📊 Konsolide BOM
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">

        {{-- ═══════════════ SOL MENÜ (MALZEME KATALOĞU) ═══════════════ --}}
        <div class="hidden lg:block lg:col-span-1 sticky top-6">
            <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm overflow-hidden flex flex-col h-[calc(100vh-12rem)]">
                <div class="p-4 border-b border-slate-200 bg-slate-50/50">
                    <h3 class="text-sm font-bold text-slate-900 mb-2">Malzeme Kataloğu</h3>
                    <input wire:model.live.debounce.300ms="globalMaterialSearch" type="text"
                           class="w-full px-3 py-2 text-sm border border-slate-200 rounded-[6px] focus:ring-slate-500 focus:border-slate-500" placeholder="Arama yapın...">
                    <p class="text-[10px] text-slate-500 mt-2 leading-tight">İstediğiniz malzemeyi tutarak sağdaki tabloya sürükleyin veya tıklayarak ekleyin.</p>
                </div>

                <div class="flex-1 overflow-y-auto p-2 space-y-1">
                    @forelse($globalMaterialResults as $gmr)
                        <div draggable="true"
                             x-on:dragstart="event.dataTransfer.setData('item_id', {{ $gmr['id'] }}); event.dataTransfer.setData('item_type', '{{ $gmr['type'] }}'); event.dataTransfer.effectAllowed = 'copy';"
                             wire:click="addMaterialFromDrag({{ $gmr['id'] }}, '{{ $gmr['type'] }}')"
                             class="p-2 bg-white hover:bg-indigo-50 border border-transparent hover:border-indigo-100 rounded-[6px] cursor-grab active:cursor-grabbing transition-colors flex items-center gap-2 group">

                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold text-slate-900 truncate">{{ $gmr['name'] }}</div>
                                <div class="text-[10px] text-slate-500 font-mono mt-0.5">{{ $gmr['code'] }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-[10px] font-bold {{ $gmr['type'] === 'recipe' ? 'text-indigo-600' : 'text-emerald-600' }}">
                                    {{ number_format($gmr['unit_price'] ?? 0, 2, ',', '.') }} ₺
                                </div>
                                @if($gmr['type'] === 'recipe')
                                    <div class="text-[9px] text-indigo-400 bg-indigo-50 px-1 rounded inline-block">Y.Mamul</div>
                                @endif
                            </div>
                        </div>
                    @empty
                        @if(strlen($globalMaterialSearch) >= 2)
                        <div class="p-4 text-center text-xs text-slate-500">
                            Sonuç bulunamadı.
                        </div>
                        @else
                        <div class="p-4 text-center text-xs text-slate-400">
                            <div class="text-2xl mb-1">🔍</div>
                            Arama yapın...
                        </div>
                        @endif
                    @endforelse
                </div>
                <div class="p-3 border-t border-slate-200 bg-slate-50">
                    <button wire:click="openQuickMaterialModal" class="w-full py-2 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded hover:bg-slate-50 transition-colors shadow-sm">
                        + Yeni Malzeme Ekle
                    </button>
                </div>
            </div>
        </div>

        {{-- ═══════════════ SAĞ ALAN (REÇETE İÇERİĞİ) ═══════════════ --}}
        <div class="lg:col-span-3 space-y-4">

            @if($viewMode === 'bom' && $recipeId)
    {{-- ═══════════════ KONSOLİDE BOM ═══════════════ --}}
    <div class="bg-white rounded-xl border overflow-x-auto shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Stok Kodu</th>
                    <th class="px-4 py-3 text-left">Malzeme</th>
                    <th class="px-4 py-3 text-center">Toplam Miktar</th>
                    <th class="px-4 py-3 text-center">Birim</th>
                    <th class="px-4 py-3 text-left">Operasyonlar</th>
                    <th class="px-4 py-3 text-right">Birim Fiyat</th>
                    <th class="px-4 py-3 text-right">Maliyet</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @php $bomTotal = 0; @endphp
                @foreach($this->consolidatedBom as $bom)
                @php $bomTotal += $bom['total_cost']; @endphp
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
                    <td class="px-4 py-3 text-right text-gray-600">
                        @if($bom['unit_price'])
                            {{ number_format($bom['unit_price'], 2, ',', '.') }} ₺
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right font-bold">
                        @if($bom['unit_price'])
                            {{ number_format($bom['total_cost'], 2, ',', '.') }} ₺
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                <tr>
                    <td colspan="6" class="px-4 py-3 text-right text-sm font-bold text-gray-700 uppercase">Toplam Maliyet</td>
                    <td class="px-4 py-3 text-right text-lg font-bold text-gray-900">{{ number_format($bomTotal, 2, ',', '.') }} ₺</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @else
    {{-- ═══════════════ REÇETE SATIRLARI (Desktop) ═══════════════ --}}
    <div class="hidden md:block bg-white rounded-xl border shadow-sm transition-colors duration-200"
         x-data="{ isDragOver: false }"
         x-on:dragover.prevent="isDragOver = true"
         x-on:dragleave="isDragOver = false"
         x-on:drop="isDragOver = false; $wire.addMaterialFromDrag(event.dataTransfer.getData('item_id'), event.dataTransfer.getData('item_type'))"
         x-bind:class="isDragOver ? 'border-indigo-500 bg-indigo-50/10 ring-4 ring-indigo-500/20' : 'border-slate-200'">
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="recipeTable" style="table-layout: fixed;">
                <style>
                    #recipeTable .text-xs { font-size: 11px !important; }
                    #recipeTable .text-sm { font-size: 13px !important; }
                    #recipeTable th, #recipeTable td { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
                </style>
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-3 py-3 text-left" style="width:10%">Operasyon</th>
                        <th class="px-3 py-3 text-left" style="width:10%">Kullanılan Yer</th>
                        <th class="px-3 py-3 text-left" style="width:18%">Malzeme</th>
                        <th class="px-3 py-3 text-left" style="width:10%">Hesap Tipi</th>
                        <th class="px-3 py-3 text-center" style="width:6%">En</th>
                        <th class="px-3 py-3 text-center" style="width:6%">Boy</th>
                        <th class="px-3 py-3 text-center" style="width:6%">Yük.</th>
                        <th class="px-3 py-3 text-center" style="width:6%">Adet</th>
                        <th class="px-3 py-3 text-center" style="width:6%">Fire</th>
                        <th class="px-3 py-3 text-center" style="width:8%">Sonuç</th>
                        <th class="px-3 py-3 text-right" style="width:8%">Maliyet</th>
                        <th class="px-3 py-3 text-center" style="width:6%">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($lines as $i => $line)
                    @if($viewMode === 'all' || $line['operation'] === $viewMode)
                    @php $lineCost = (float)($line['unit_price'] ?? 0) * (float)($line['calculated_qty'] ?? 0); @endphp
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
                        <td class="px-3 py-2" style="overflow:visible">
                            @if(!empty($line['material_id']) || !empty($line['sub_recipe_id']))
                                <div class="flex items-center gap-1">
                                    <span class="text-xs truncate flex-1" title="{{ $line['material_label'] }}">{{ $line['material_label'] }}</span>
                                    <button wire:click="$set('lines.{{ $i }}.material_id', null); $set('lines.{{ $i }}.sub_recipe_id', null)" class="text-gray-400 hover:text-red-500 text-xs flex-shrink-0">✕</button>
                                </div>
                            @else
                                <div class="relative">
                                    <input wire:keyup.debounce.300ms="searchMaterial({{ $i }}, $event.target.value)" type="text"
                                           class="w-full px-2 py-1.5 text-xs border rounded" placeholder="Malzeme ara...">
                                    @if($searchingLineIndex === $i && count($materialSearchResults) > 0)
                                    <div class="absolute z-30 w-64 mt-1 bg-white border rounded-lg shadow-lg max-h-40 overflow-y-auto">
                                        @foreach($materialSearchResults as $mr)
                                        <button wire:click="selectMaterial({{ $i }}, {{ $mr['id'] }}, {{ isset($mr['is_sub_recipe']) && $mr['is_sub_recipe'] ? 'true' : 'false' }})"
                                                class="w-full text-left px-3 py-2 text-xs hover:bg-gray-50 border-b last:border-0 {{ isset($mr['is_sub_recipe']) && $mr['is_sub_recipe'] ? 'bg-indigo-50' : '' }}">
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
                        </td>
                        {{-- Maliyet --}}
                        <td class="px-3 py-2 text-right">
                            @if($line['unit_price'] > 0)
                                <div class="font-bold text-gray-900 text-xs">{{ number_format($lineCost, 2, ',', '.') }} ₺</div>
                                <div class="text-[10px] text-gray-400">{{ number_format($line['unit_price'], 2, ',', '.') }}/br</div>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
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
                    @endif
                    @empty
                    <tr>
                        <td colspan="12" class="px-4 py-12 text-center text-gray-400">
                            <div class="text-4xl mb-2">📝</div>
                            <p>Henüz satır eklenmedi.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Alt Bar --}}
        <div class="px-4 py-3 border-t bg-gray-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <button wire:click="addLine" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm font-medium bg-white border rounded-lg hover:bg-gray-50 transition-colors">
                + Satır Ekle
            </button>
            <div class="flex items-center gap-4 text-xs text-gray-500">
                <span>{{ count($lines) }} satır</span>
                @if(count($lines) > 0)
                <span class="font-bold text-gray-900 text-sm">Toplam: {{ number_format($this->totalCost, 2, ',', '.') }} ₺</span>
                <button wire:click="recalculateAll" class="text-blue-600 hover:underline">🔄 Tümünü Hesapla</button>
                @endif
            </div>
        </div>
    </div>

    {{-- ═══════════════ MOBİL KART GÖRÜNÜMܠ═══════════════ --}}
    <div class="md:hidden space-y-3">
        @forelse($lines as $i => $line)
        @if($viewMode === 'all' || $line['operation'] === $viewMode)
        @php $lineCost = (float)($line['unit_price'] ?? 0) * (float)($line['calculated_qty'] ?? 0); @endphp
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm" wire:key="mobile-line-{{ $i }}">
            {{-- Üst: Operasyon + Malzeme + İşlem --}}
            <div class="flex items-start justify-between gap-2 mb-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-indigo-100 text-indigo-700">
                            {{ \App\Models\RecipeLine::OPERATIONS[$line['operation']] ?? $line['operation'] }}
                        </span>
                        @if($line['usage_area'])
                            <span class="text-[10px] text-gray-400">{{ $line['usage_area'] }}</span>
                        @endif
                    </div>
                    <h3 class="text-sm font-medium text-gray-900 truncate">
                        {{ $line['material_label'] ?: 'Malzeme seçilmedi' }}
                    </h3>
                    <div class="text-[10px] text-gray-400 mt-0.5">
                        {{ \App\Models\RecipeLine::CALC_TYPES[$line['calc_type']] ?? $line['calc_type'] }}
                    </div>
                </div>
                <div class="flex gap-1">
                    <button wire:click="duplicateLine({{ $i }})" class="p-1.5 text-gray-400 hover:text-blue-600">📋</button>
                    <button wire:click="removeLine({{ $i }})" class="p-1.5 text-gray-400 hover:text-red-600">🗑️</button>
                </div>
            </div>

            {{-- Ölçüler + Sonuç Grid --}}
            <div class="grid grid-cols-2 gap-2 text-xs border-t border-gray-100 pt-3">
                @if($line['calc_type'] !== 'fixed_qty')
                <div class="flex justify-between"><span class="text-gray-500">En</span><span class="font-medium">{{ $line['width_cm'] ?? '—' }} cm</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Boy</span><span class="font-medium">{{ $line['length_cm'] ?? '—' }} cm</span></div>
                @if($line['calc_type'] === 'volume_m3')
                <div class="flex justify-between"><span class="text-gray-500">Yükseklik</span><span class="font-medium">{{ $line['height_cm'] ?? '—' }} cm</span></div>
                @endif
                <div class="flex justify-between"><span class="text-gray-500">Adet</span><span class="font-medium">{{ $line['pieces'] ?? 1 }}</span></div>
                @else
                <div class="flex justify-between"><span class="text-gray-500">Sabit Miktar</span><span class="font-medium">{{ $line['constant_qty'] ?? '—' }}</span></div>
                @endif
                <div class="flex justify-between"><span class="text-gray-500">Sonuç</span>
                    <span class="font-bold text-gray-900">{{ number_format($line['calculated_qty'] ?? 0, 4) }} {{ \App\Models\Material::UNITS[$line['calculated_unit'] ?? 'pcs'] ?? '' }}</span>
                </div>
                <div class="flex justify-between"><span class="text-gray-500">Maliyet</span>
                    <span class="font-bold {{ $lineCost > 0 ? 'text-emerald-600' : 'text-gray-400' }}">
                        {{ $lineCost > 0 ? number_format($lineCost, 2, ',', '.') . ' ₺' : '—' }}
                    </span>
                </div>
            </div>
        </div>
        @endif
        @empty
        <div class="text-center py-12 text-gray-400">
            <div class="text-4xl mb-2">📝</div>
            <p>Henüz satır eklenmedi.</p>
        </div>
        @endforelse

        {{-- Mobil Alt Bar --}}
        <div class="flex flex-col gap-3">
            <button wire:click="addLine" class="w-full px-4 py-3 text-sm font-medium bg-white border rounded-lg hover:bg-gray-50">
                + Satır Ekle
            </button>
            @if(count($lines) > 0)
            <div class="bg-gray-900 text-white rounded-xl p-4 text-center">
                <div class="text-xs text-gray-400 uppercase">Toplam Reçete Maliyeti</div>
                <div class="text-2xl font-bold mt-1">{{ number_format($this->totalCost, 2, ',', '.') }} ₺</div>
                <button wire:click="recalculateAll" class="text-xs text-blue-300 hover:text-blue-100 mt-2 underline">🔄 Tümünü Hesapla</button>
            </div>
            @endif
        </div>
    </div>
    @endif

    </div> <!-- // SAĞ ALAN BİTİŞİ -->
    </div> <!-- // GRID BİTİŞİ -->

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
                        <input wire:model="qmCode" type="text" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg">
                        @error('qmCode') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Kategori</label>
                        <select wire:model.live="qmCategory" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg">
                            @foreach(\App\Models\Material::CATEGORIES as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Malzeme Adı *</label>
                    <input wire:model="qmName" type="text" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg">
                    @error('qmName') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Birim</label>
                        <select wire:model="qmUnit" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg">
                            @foreach(\App\Models\Material::UNITS as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Fire</label>
                        <input wire:model="qmWasteRate" type="number" step="0.01" min="0" max="1"
                               class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg">
                    </div>
                </div>
                @if($qmCategory === 'fabric')
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Kumaş Eni (cm)</label>
                    <input wire:model="qmFabricWidth" type="number" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg" placeholder="140">
                </div>
                @endif
            </div>
            <div class="p-6 border-t flex justify-end gap-3">
                <button wire:click="$set('showQuickMaterialModal', false)" class="px-4 py-3 sm:py-2 text-sm border rounded-lg hover:bg-gray-50">İptal</button>
                <button wire:click="saveQuickMaterial" class="px-4 py-3 sm:py-2 text-sm bg-gray-900 text-white rounded-lg hover:bg-gray-800">Kaydet & Ata</button>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════════ WHAT-IF SİMÜLATÖR MODAL ═══════════════ --}}
    @if($showSimModal)
    <div class="fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showSimModal', false)">
        <div class="bg-white rounded-2xl w-full max-w-md">
            <div class="p-6 border-b">
                <h2 class="text-lg font-bold flex items-center gap-2">🔮 What-If Maliyet Simülatörü</h2>
                <p class="text-xs text-gray-500 mt-1">Fiyat artış/azalış senaryolarını test edin.</p>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Hangi Kategori Etkilenecek?</label>
                    <select wire:model.live="simCategory" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg">
                        <option value="all">Tümü (Tüm Malzemeler)</option>
                        @foreach(\App\Models\Material::CATEGORIES as $k => $v)
                            <option value="{{ $k }}">{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Değişim Oranı (%)</label>
                    <div class="flex items-center gap-3">
                        <input wire:model.live.debounce.300ms="simPercent" type="range" min="-50" max="100" step="5" class="flex-1">
                        <input wire:model.live="simPercent" type="number" class="w-20 px-3 py-2 border rounded-lg text-sm text-center">
                    </div>
                </div>
                <div class="flex items-center gap-2 mt-2">
                    <input wire:model.live="simIncludeSubRecipes" type="checkbox" id="simSubR" class="rounded text-indigo-600 border-gray-300 focus:ring-indigo-500">
                    <label for="simSubR" class="text-xs text-gray-600">Alt reçeteleri de (yarım mamuller) etki alanına dahil et</label>
                </div>

                <div class="mt-6 p-4 bg-gray-50 rounded-xl border border-gray-200">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-xs text-gray-500">Mevcut Maliyet:</span>
                        <span class="font-medium text-sm">{{ number_format($this->totalCost, 2, ',', '.') }} ₺</span>
                    </div>
                    <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                        <span class="text-sm font-bold text-gray-900">Sanal Senaryo:</span>
                        @php $simDiff = $this->simulatedCost - $this->totalCost; @endphp
                        <div class="text-right">
                            <span class="text-lg font-bold {{ $simDiff > 0 ? 'text-red-600' : ($simDiff < 0 ? 'text-emerald-600' : 'text-gray-900') }}">
                                {{ number_format($this->simulatedCost, 2, ',', '.') }} ₺
                            </span>
                            @if($simDiff != 0)
                            <div class="text-[10px] {{ $simDiff > 0 ? 'text-red-500' : 'text-emerald-500' }}">
                                {{ $simDiff > 0 ? '+' : '' }}{{ number_format($simDiff, 2, ',', '.') }} ₺ fark
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-6 border-t flex justify-end gap-3">
                <button wire:click="$set('showSimModal', false)" class="px-4 py-3 sm:py-2 text-sm bg-gray-900 text-white rounded-lg hover:bg-gray-800">Kapat</button>
            </div>
        </div>
    </div>
    @endif
</div>
