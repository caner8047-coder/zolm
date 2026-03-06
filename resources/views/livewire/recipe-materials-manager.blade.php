<div class="px-4 lg:px-6 py-6 mx-auto max-w-7xl">
    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-sm" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-red-800 text-sm">{{ session('error') }}</div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-gray-900">⚗️ Reçete Hazırlama</h1>
            <p class="text-sm lg:text-base text-gray-500 mt-1">Ham madde stok kartları yönetimi ve reçete oluşturma</p>
        </div>
    </div>

    {{-- KPI Kartları --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-3 lg:p-4 text-center shadow-sm">
            <div class="text-2xl font-bold text-gray-900">{{ $stats['total'] }}</div>
            <div class="text-xs text-gray-500">Toplam Malzeme</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3 lg:p-4 text-center shadow-sm">
            <div class="text-2xl font-bold text-blue-600">{{ $stats['fabric'] }}</div>
            <div class="text-xs text-gray-500">Kumaş</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3 lg:p-4 text-center shadow-sm">
            <div class="text-2xl font-bold text-amber-600">{{ $stats['foam'] }}</div>
            <div class="text-xs text-gray-500">Sünger</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3 lg:p-4 text-center shadow-sm">
            <div class="text-2xl font-bold text-emerald-600">{{ $stats['wood'] }}</div>
            <div class="text-xs text-gray-500">Ahşap</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3 lg:p-4 text-center shadow-sm">
            <div class="text-2xl font-bold text-gray-600">{{ $stats['hardware'] }}</div>
            <div class="text-xs text-gray-500">Hırdavat</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3 lg:p-4 text-center shadow-sm">
            <div class="text-2xl font-bold text-purple-600">{{ $stats['recipes'] }}</div>
            <div class="text-xs text-gray-500">Reçete</div>
        </div>
    </div>

    {{-- Tab Seçimi --}}
    <div class="flex gap-2 mb-4 border-b border-gray-200">
        <button wire:click="$set('activeTab', 'materials')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'materials' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            📦 Ham Madde Kartları
        </button>
        <button wire:click="$set('activeTab', 'recipes')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'recipes' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            📋 Reçeteler
        </button>
    </div>

    @if($activeTab === 'materials')
    {{-- ═══════════════ HAM MADDE KARTLARI TAB ═══════════════ --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        {{-- Toolbar --}}
        <div class="p-4 border-b border-gray-100 flex flex-col sm:flex-row gap-3">
            {{-- Arama --}}
            <div class="flex-1">
                <input wire:model.live.debounce.300ms="search" type="text"
                       placeholder="Stok kodu veya malzeme adı ara..."
                       class="w-full px-4 py-2.5 text-base sm:text-sm border rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-gray-900">
            </div>
            {{-- Filtreler --}}
            <select wire:model.live="categoryFilter" class="w-full sm:w-auto px-3 py-2.5 text-base sm:text-sm border rounded-lg">
                <option value="all">Tüm Kategoriler</option>
                @foreach(\App\Models\Material::CATEGORIES as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="statusFilter" class="w-full sm:w-auto px-3 py-2.5 text-base sm:text-sm border rounded-lg">
                <option value="active">Aktif</option>
                <option value="inactive">Pasif</option>
                <option value="all">Tümü</option>
            </select>
            {{-- Aksiyonlar --}}
            <div class="flex gap-2">
                <button wire:click="openCreateModal"
                        class="w-full sm:w-auto px-4 py-3 sm:py-2.5 text-sm font-medium bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors">
                    + Yeni Malzeme
                </button>
                <button wire:click="$set('showImportModal', true)"
                        class="w-full sm:w-auto px-4 py-3 sm:py-2.5 text-sm font-medium border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                    📥 Excel Import
                </button>
            </div>
        </div>

        {{-- Toplu İşlem Bar --}}
        @if(count($selectedMaterials) > 0)
        <div class="px-4 py-3 bg-blue-50 border-b flex flex-col sm:flex-row sm:items-center gap-3 flex-wrap">
            <span class="text-sm font-medium text-blue-800">{{ count($selectedMaterials) }} malzeme seçili</span>
            <select wire:model="bulkAction" class="w-full sm:w-auto px-3 py-2 text-base sm:text-sm border rounded-lg">
                <option value="">İşlem seç...</option>
                <option value="activate">Aktifleştir</option>
                <option value="deactivate">Pasife Al</option>
                <option value="change_waste">Fire Değiştir</option>
                <option value="change_category">Kategori Değiştir</option>
                <option value="delete">Sil</option>
            </select>
            @if($bulkAction === 'change_waste')
                <input wire:model="bulkWasteRate" type="number" step="0.01" min="0" max="1" placeholder="Yeni fire (0.10)"
                       class="w-full sm:w-32 px-3 py-2 text-base sm:text-sm border rounded-lg">
            @endif
            @if($bulkAction === 'change_category')
                <select wire:model="bulkCategory" class="w-full sm:w-auto px-3 py-2 text-base sm:text-sm border rounded-lg">
                    @foreach(\App\Models\Material::CATEGORIES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            @endif
            <button wire:click="applyBulkAction" wire:confirm="Seçili malzemelere bu işlemi uygulamak istediğinize emin misiniz?"
                    class="w-full sm:w-auto px-3 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                Uygula
            </button>
        </div>
        @endif

        {{-- Desktop Tablo --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm" id="materialsTable" style="table-layout: fixed; width: 100%;">
                <style>
                    #materialsTable .text-xs { font-size: 11px !important; }
                    #materialsTable .text-sm { font-size: 13px !important; }
                    #materialsTable th, #materialsTable td { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
                </style>
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left" style="width:32px"><input type="checkbox" wire:model.live="selectAll" class="rounded"></th>
                        <th class="px-4 py-3 text-left" style="width:12%">Stok Kodu</th>
                        <th class="px-4 py-3 text-left" style="width:25%">Malzeme Adı</th>
                        <th class="px-4 py-3 text-left" style="width:10%">Kategori</th>
                        <th class="px-4 py-3 text-center" style="width:8%">Birim</th>
                        <th class="px-4 py-3 text-center" style="width:7%">Fire %</th>
                        <th class="px-4 py-3 text-center" style="width:8%">Kumaş Eni</th>
                        <th class="px-4 py-3 text-right" style="width:10%">Fiyat</th>
                        <th class="px-4 py-3 text-center" style="width:8%">Durum</th>
                        <th class="px-4 py-3 text-right" style="width:8%">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($materials as $material)
                    <tr class="hover:bg-gray-50 {{ !$material->is_active ? 'opacity-50' : '' }}" wire:key="material-{{ $material->id }}">
                        <td class="px-4 py-3">
                            <input type="checkbox" wire:model.live="selectedMaterials" value="{{ $material->id }}" class="rounded">
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $material->code }}</td>
                        <td class="px-4 py-3 font-medium text-gray-900 truncate" title="{{ $material->name }}">{{ $material->name }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 text-xs rounded-full
                                {{ match($material->category) {
                                    'fabric' => 'bg-blue-100 text-blue-700',
                                    'foam' => 'bg-amber-100 text-amber-700',
                                    'wood' => 'bg-emerald-100 text-emerald-700',
                                    'hardware' => 'bg-gray-100 text-gray-700',
                                    'packaging' => 'bg-purple-100 text-purple-700',
                                    'textile' => 'bg-indigo-100 text-indigo-700',
                                    'lining' => 'bg-pink-100 text-pink-700',
                                    default => 'bg-gray-100 text-gray-600',
                                } }}">
                                {{ $material->category_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ $material->unit_label }}</td>
                        <td class="px-4 py-3 text-center text-gray-600">%{{ number_format($material->default_waste_rate * 100, 0) }}</td>
                        <td class="px-4 py-3 text-center text-gray-600">
                            {{ $material->fabric_width_cm ? $material->fabric_width_cm . ' cm' : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-gray-600">
                            {{ $material->unit_price ? number_format($material->unit_price, 2, ',', '.') . ' ₺' : '—' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button wire:click="toggleActive({{ $material->id }})" class="text-xs">
                                @if($material->is_active)
                                    <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded-full">Aktif</span>
                                @else
                                    <span class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full">Pasif</span>
                                @endif
                            </button>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                <button wire:click="editMaterial({{ $material->id }})"
                                        class="p-1.5 text-gray-400 hover:text-gray-900 rounded" title="Düzenle">
                                    ✏️
                                </button>
                                <button wire:click="deleteMaterial({{ $material->id }})"
                                        wire:confirm="Bu malzemeyi silmek istediğinize emin misiniz?"
                                        class="p-1.5 text-gray-400 hover:text-red-600 rounded" title="Sil">
                                    🗑️
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-12 text-center text-gray-400">
                            <div class="text-4xl mb-2">📦</div>
                            <p>Henüz malzeme eklenmedi.</p>
                            <button wire:click="openCreateModal" class="mt-2 text-sm text-blue-600 hover:underline">
                                İlk malzemeyi ekle →
                            </button>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobil Kart Görünümü --}}
        <div class="md:hidden divide-y divide-gray-100">
            @forelse($materials as $material)
            <div class="p-4 {{ !$material->is_active ? 'opacity-50' : '' }}" wire:key="material-card-{{ $material->id }}">
                <div class="flex items-start gap-3">
                    <input type="checkbox" wire:model.live="selectedMaterials" value="{{ $material->id }}" class="rounded mt-1">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <h3 class="text-sm font-medium text-gray-900 truncate">{{ $material->name }}</h3>
                            <span class="px-1.5 py-0.5 text-[10px] rounded-full {{ match($material->category) {
                                'fabric' => 'bg-blue-100 text-blue-700',
                                'foam' => 'bg-amber-100 text-amber-700',
                                'wood' => 'bg-emerald-100 text-emerald-700',
                                default => 'bg-gray-100 text-gray-600',
                            } }}">{{ $material->category_label }}</span>
                        </div>
                        <div class="text-xs text-gray-500 font-mono">{{ $material->code }}</div>
                        <div class="grid grid-cols-2 gap-2 mt-2 text-xs">
                            <div class="flex justify-between"><span class="text-gray-500">Birim</span><span class="font-medium">{{ $material->unit_label }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Fire</span><span class="font-medium">%{{ number_format($material->default_waste_rate * 100, 0) }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Eni</span><span class="font-medium">{{ $material->fabric_width_cm ? $material->fabric_width_cm . ' cm' : '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Fiyat</span><span class="font-medium">{{ $material->unit_price ? number_format($material->unit_price, 2, ',', '.') . ' ₺' : '—' }}</span></div>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <button wire:click="editMaterial({{ $material->id }})" class="p-1.5 text-gray-400 hover:text-gray-900">✏️</button>
                        <button wire:click="deleteMaterial({{ $material->id }})" wire:confirm="Sil?" class="p-1.5 text-gray-400 hover:text-red-600">🗑️</button>
                    </div>
                </div>
            </div>
            @empty
            <div class="p-12 text-center text-gray-400">
                <div class="text-4xl mb-2">📦</div>
                <p>Henüz malzeme eklenmedi.</p>
            </div>
            @endforelse
        </div>

        <div class="px-4 py-3 border-t">{{ $materials->links() }}</div>
    </div>

    @else
    {{-- ═══════════════ REÇETELER TAB ═══════════════ --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="p-4 border-b flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="text-lg font-semibold text-gray-900">Reçeteler</h2>
            <div class="flex gap-2">
                <button wire:click="openRecipeImportModal"
                        class="w-full sm:w-auto px-4 py-3 sm:py-2.5 text-sm font-medium border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                    📥 Excel'den Reçete Import
                </button>
                <button wire:click="createRecipe"
                        class="w-full sm:w-auto px-4 py-3 sm:py-2.5 text-sm font-medium bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors">
                    + Yeni Reçete
                </button>
            </div>
        </div>

        <div class="divide-y">
            @forelse($recipes as $recipe)
            <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-gray-50 transition-colors">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="font-medium text-gray-900">{{ $recipe->name }}</h3>
                        <span class="px-2 py-0.5 text-xs rounded-full {{ $recipe->status_color }}">{{ $recipe->status_label }}</span>
                        <span class="text-xs text-gray-400">{{ $recipe->version }}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 mt-1">
                        @if($recipe->product)
                            <span>🏷️ {{ \Illuminate\Support\Str::limit($recipe->product->product_name, 40) }}</span>
                        @endif
                        <span>{{ $recipe->lines->count() }} satır</span>
                        @if($recipe->total_cost > 0)
                            <span class="font-bold text-gray-800">💰 {{ number_format($recipe->total_cost, 2, ',', '.') }} ₺</span>
                        @endif
                        <span>{{ $recipe->updated_at->diffForHumans() }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button wire:click="editRecipe({{ $recipe->id }})"
                            class="w-full sm:w-auto px-3 py-2 sm:py-1.5 text-sm border rounded-lg hover:bg-gray-50 transition-colors">
                        Düzenle
                    </button>
                    <button wire:click="deleteRecipe({{ $recipe->id }})"
                            wire:confirm="Bu reçeteyi silmek istediğinize emin misiniz?"
                            class="px-3 py-2 sm:py-1.5 text-sm border border-red-200 text-red-600 rounded-lg hover:bg-red-50 transition-colors">
                        Sil
                    </button>
                </div>
            </div>
            @empty
            <div class="p-12 text-center text-gray-400">
                <div class="text-4xl mb-2">📋</div>
                <p>Henüz reçete oluşturulmadı.</p>
                <button wire:click="createRecipe" class="mt-2 text-sm text-blue-600 hover:underline">
                    İlk reçeteyi oluştur →
                </button>
            </div>
            @endforelse
        </div>
    </div>
    @endif

    {{-- ═══════════════ MALZEME OLUŞTUR/DÜZENLE MODAL ═══════════════ --}}
    @if($showCreateModal)
    <div class="fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showCreateModal', false)">
        <div class="bg-white rounded-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b">
                <h2 class="text-lg font-bold">{{ $editingMaterialId ? 'Malzeme Düzenle' : 'Yeni Malzeme' }}</h2>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Stok Kodu *</label>
                        <input wire:model="formCode" type="text" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg" placeholder="HMKMŞ001">
                        @error('formCode') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Kategori *</label>
                        <select wire:model.live="formCategory" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg">
                            @foreach(\App\Models\Material::CATEGORIES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Malzeme Adı *</label>
                    <input wire:model="formName" type="text" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg" placeholder="HM KUMAŞ DİĞER...">
                    @error('formName') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Birim</label>
                        <select wire:model="formUnit" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg">
                            @foreach(\App\Models\Material::UNITS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Varsayılan Fire</label>
                        <div class="flex items-center gap-2">
                            <input wire:model="formWasteRate" type="number" step="0.01" min="0" max="1"
                                   class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg" placeholder="0.10">
                            <span class="text-xs text-gray-400 whitespace-nowrap">= %{{ number_format(($formWasteRate ?? 0) * 100, 0) }}</span>
                        </div>
                    </div>
                </div>

                {{-- Kumaş-Spesifik Alanlar --}}
                @if($formCategory === 'fabric' || $formCategory === 'textile')
                <div class="p-4 bg-blue-50 rounded-lg space-y-3">
                    <h4 class="text-xs font-semibold text-blue-700 uppercase">Kumaş Ayarları</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-blue-600 mb-1">Kumaş Eni (cm)</label>
                            <input wire:model="formFabricWidth" type="number" step="0.5"
                                   class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg" placeholder="140">
                        </div>
                        <div>
                            <label class="block text-xs text-blue-600 mb-1">Metre Hesap Yöntemi</label>
                            <select wire:model="formFabricMethod" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg">
                                @foreach(\App\Models\Material::FABRIC_METHODS as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Sünger-Spesifik Alanlar --}}
                @if($formCategory === 'foam')
                <div class="p-4 bg-amber-50 rounded-lg space-y-3">
                    <h4 class="text-xs font-semibold text-amber-700 uppercase">Sünger Ayarları</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-amber-600 mb-1">Yoğunluk (kg/m³)</label>
                            <input wire:model="formDensity" type="number" step="0.1"
                                   class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg" placeholder="15">
                        </div>
                        <div>
                            <label class="block text-xs text-amber-600 mb-1">Kalınlık (cm)</label>
                            <input wire:model="formThickness" type="number" step="0.5"
                                   class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg" placeholder="10">
                        </div>
                    </div>
                </div>
                @endif

                {{-- Fiyat --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Birim Fiyat</label>
                        <input wire:model="formUnitPrice" type="number" step="0.01"
                               class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg" placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Para Birimi</label>
                        <select wire:model="formCurrency" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg">
                            <option value="TRY">₺ TRY</option>
                            <option value="USD">$ USD</option>
                            <option value="EUR">€ EUR</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Tedarikçi</label>
                    <input wire:model="formSupplier" type="text" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Notlar</label>
                    <textarea wire:model="formNotes" rows="2" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg"></textarea>
                </div>
            </div>
            <div class="p-6 border-t flex flex-col sm:flex-row sm:justify-end gap-3">
                <button wire:click="$set('showCreateModal', false)" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm border rounded-lg hover:bg-gray-50">İptal</button>
                <button wire:click="saveMaterial" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm bg-gray-900 text-white rounded-lg hover:bg-gray-800">
                    {{ $editingMaterialId ? 'Güncelle' : 'Kaydet' }}
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════════ EXCEL IMPORT MODAL ═══════════════ --}}
    @if($showImportModal)
    <div class="fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showImportModal', false)">
        <div class="bg-white rounded-2xl w-full max-w-md">
            <div class="p-6 border-b">
                <h2 class="text-lg font-bold">📥 Excel Import</h2>
                <p class="text-xs text-gray-500 mt-1">Stok kartları Excel dosyanızı yükleyin (A: Stok Kodu, B: Açıklama)</p>
            </div>
            <div class="p-6">
                <input wire:model="importFile" type="file" accept=".xlsx,.xls"
                       class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg">
                @error('importFile') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </div>
            <div class="p-6 border-t flex flex-col sm:flex-row sm:justify-end gap-3">
                <button wire:click="$set('showImportModal', false)" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm border rounded-lg hover:bg-gray-50">İptal</button>
                <button wire:click="importExcel" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm bg-gray-900 text-white rounded-lg hover:bg-gray-800">
                    Import Et
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════════ REÇETE IMPORT MODAL ═══════════════ --}}
    @if($showRecipeImportModal)
    <div class="fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showRecipeImportModal', false)">
        <div class="bg-white rounded-2xl w-full max-w-md">
            <div class="p-6 border-b">
                <h2 class="text-lg font-bold">📋 Excel'den Reçete Import</h2>
                <p class="text-xs text-gray-500 mt-1">"ürün ağacı" sayfası olan bir Excel dosyası yükleyin. Eksik malzemeler otomatik oluşturulur.</p>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Reçete Adı *</label>
                    <input wire:model="recipeImportName" type="text" class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg" placeholder="Berjer Alaves Reçetesi">
                    @error('recipeImportName') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Excel Dosyası *</label>
                    <input wire:model="recipeImportFile" type="file" accept=".xlsx,.xls"
                           class="w-full px-3 py-2.5 text-base sm:text-sm border rounded-lg">
                    @error('recipeImportFile') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>
                <div class="bg-blue-50 rounded-lg p-3 text-xs text-blue-700">
                    <strong>Beklenen Excel yapısı:</strong>
                    <ul class="mt-1 space-y-0.5 list-disc list-inside">
                        <li>"ürün ağacı" sayfası (2. sayfa)</li>
                        <li>A = Operasyon (KUMAŞ, AHŞAP, SÜNGER...)</li>
                        <li>B = Stok Kodu, C = Malzeme Adı</li>
                        <li>F = En, G = Boy, H = Yükseklik, I = Adet</li>
                        <li>K = Hesaplanan miktar, L = Fire oranı</li>
                    </ul>
                </div>
            </div>
            <div class="p-6 border-t flex flex-col sm:flex-row sm:justify-end gap-3">
                <button wire:click="$set('showRecipeImportModal', false)" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm border rounded-lg hover:bg-gray-50">İptal</button>
                <button wire:click="importRecipeExcel" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm bg-gray-900 text-white rounded-lg hover:bg-gray-800">
                    Import Et
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
