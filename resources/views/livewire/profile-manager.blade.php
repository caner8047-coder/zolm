<div>
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Profil Yönetimi</h1>
            <p class="text-gray-500 mt-1">Dönüşüm profillerini oluşturun ve yönetin</p>
        </div>
        <div class="flex space-x-3">
            <button 
                wire:click="create"
                class="px-4 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors"
            >
                + Manuel Profil
            </button>
            <a 
                href="{{ route('profile.wizard') }}"
                class="px-4 py-2 bg-purple-600 text-white font-medium rounded-lg hover:bg-purple-700 transition-colors flex items-center"
            >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                AI ile Profil Oluştur
            </a>
        </div>
    </div>

    <!-- Flash Messages -->
    @if(session('success'))
    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700">
        {{ session('success') }}
    </div>
    @endif

    <!-- Profiles Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($this->profiles as $profile)
        <div class="bg-white rounded-lg border border-gray-200 p-6 {{ $profile->is_ai_generated ? 'ring-2 ring-purple-200' : '' }}">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <div class="flex items-center space-x-2">
                        <h3 class="font-medium text-gray-900">{{ $profile->name }}</h3>
                        @if($profile->is_ai_generated)
                        <span class="px-2 py-0.5 text-xs bg-purple-100 text-purple-700 rounded flex items-center">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            AI
                        </span>
                        @endif
                    </div>
                    <div class="flex items-center space-x-2 mt-1">
                        <span class="px-2 py-0.5 text-xs rounded {{ $profile->type === 'production' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700' }}">
                            {{ $profile->type === 'production' ? 'Üretim' : 'Operasyon' }}
                        </span>
                        @if($profile->status === 'analyzing')
                        <span class="px-2 py-0.5 text-xs bg-yellow-100 text-yellow-700 rounded animate-pulse">
                            Analiz Ediliyor...
                        </span>
                        @elseif($profile->status === 'error')
                        <span class="px-2 py-0.5 text-xs bg-red-100 text-red-700 rounded">
                            Hata
                        </span>
                        @endif
                    </div>
                </div>
                @if($profile->is_default)
                <span class="px-2 py-0.5 text-xs bg-gray-900 text-white rounded">★ Varsayılan</span>
                @endif
            </div>

            <!-- AI Prompt Preview -->
            @if($profile->ai_prompt)
            <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                <p class="text-xs text-gray-600 line-clamp-2">{{ $profile->ai_prompt }}</p>
            </div>
            @endif

            <div class="flex space-x-2">
                @if(!$profile->is_ai_generated)
                <button 
                    wire:click="edit({{ $profile->id }})"
                    class="flex-1 px-3 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                >
                    Düzenle
                </button>
                @else
                <button 
                    wire:click="viewRules({{ $profile->id }})"
                    class="flex-1 px-3 py-2 text-sm text-center text-purple-700 border border-purple-300 rounded-lg hover:bg-purple-50 transition-colors"
                >
                    Kuralları Gör
                </button>
                @endif
                <button 
                    wire:click="delete({{ $profile->id }})"
                    wire:confirm="Bu profili silmek istediğinize emin misiniz?"
                    class="px-3 py-2 text-sm text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors"
                >
                    Sil
                </button>
            </div>
        </div>
        @endforeach

        <!-- Empty State or Create Card -->
        @if($this->profiles->isEmpty())
        <div class="col-span-full bg-gray-50 rounded-lg border-2 border-dashed border-gray-300 p-12 text-center">
            <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Henüz profil yok</h3>
            <p class="text-gray-500 mb-4">İlk profilinizi oluşturarak başlayın</p>
            <a href="{{ route('profile.wizard') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                AI ile Profil Oluştur
            </a>
        </div>
        @endif
    </div>

    <!-- Modal for Manual Profile -->
    @if($showModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg w-full max-w-md p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-6">
                {{ $editingId ? 'Profil Düzenle' : 'Manuel Profil Oluştur' }}
            </h2>

            <form wire:submit="save" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Profil Adı</label>
                    <input 
                        type="text" 
                        wire:model="name"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900"
                        placeholder="Örn: Varsayılan Üretim"
                    >
                    @error('name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tür</label>
                    <select wire:model="type" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="production">Üretim</option>
                        <option value="operation">Operasyon</option>
                    </select>
                </div>

                <div class="flex items-center">
                    <input 
                        type="checkbox" 
                        wire:model="isDefault"
                        id="isDefault"
                        class="w-4 h-4 border-gray-300 rounded text-gray-900 focus:ring-gray-900"
                    >
                    <label for="isDefault" class="ml-2 text-sm text-gray-600">
                        Varsayılan olarak ayarla
                    </label>
                </div>

                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-600">
                        <strong>Not:</strong> Manuel profiller varsayılan dönüşüm motorunu kullanır. 
                        Özel dönüşüm kuralları için 
                        <a href="{{ route('profile.wizard') }}" class="text-purple-600 hover:underline">AI ile Profil Oluştur</a> 
                        seçeneğini kullanın.
                    </p>
                </div>

                <div class="flex space-x-3 pt-4">
                    <button 
                        type="submit"
                        class="flex-1 px-4 py-2 bg-gray-900 text-white font-medium rounded-lg hover:bg-gray-800 transition-colors"
                    >
                        Kaydet
                    </button>
                    <button 
                        type="button"
                        wire:click="$set('showModal', false)"
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        İptal
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Rules Modal -->
    @if($showRulesModal && $viewingRules)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-3xl max-h-[80vh] overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">AI Kuralları</h2>
                    <p class="text-sm text-gray-500">{{ $viewingProfileName }}</p>
                </div>
                <button wire:click="closeRulesModal" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="p-6 overflow-y-auto flex-1">
                <!-- Input Config -->
                @if(isset($viewingRules['input']))
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">📥 Girdi Ayarları</h3>
                    <div class="bg-gray-50 rounded-lg p-4 text-sm">
                        <p><strong>Sayfa:</strong> {{ $viewingRules['input']['sheet_name'] ?? 'Varsayılan' }}</p>
                        @if(isset($viewingRules['input']['columns']))
                        <p class="mt-2"><strong>Kolonlar:</strong></p>
                        <div class="flex flex-wrap gap-1 mt-1">
                            @foreach($viewingRules['input']['columns'] as $col)
                            <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">
                                {{ is_array($col) ? ($col['name'] ?? '') : $col }}
                            </span>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                <!-- Transformations -->
                @if(isset($viewingRules['transformations']) && count($viewingRules['transformations']) > 0)
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">⚙️ Dönüşümler</h3>
                    <div class="space-y-2">
                        @foreach($viewingRules['transformations'] as $transform)
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 bg-yellow-200 text-yellow-800 rounded text-xs font-medium">
                                    {{ $transform['type'] ?? 'işlem' }}
                                </span>
                                @if(isset($transform['description']))
                                <span class="text-sm text-gray-700">{{ $transform['description'] }}</span>
                                @endif
                            </div>
                            @if(isset($transform['mapping']))
                            <div class="mt-2 text-xs text-gray-600">
                                <strong>Eşleme:</strong>
                                @foreach($transform['mapping'] as $from => $to)
                                <span class="inline-block mx-1">{{ $from }} → {{ $to }}</span>
                                @endforeach
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Outputs -->
                @if(isset($viewingRules['outputs']) && count($viewingRules['outputs']) > 0)
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">📤 Çıktı Dosyaları</h3>
                    <div class="space-y-2">
                        @foreach($viewingRules['outputs'] as $output)
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                            <p class="font-medium text-green-800">{{ $output['filename_pattern'] ?? 'dosya.xlsx' }}</p>
                            @if(isset($output['sheets']))
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach($output['sheets'] as $sheet)
                                <span class="px-2 py-0.5 bg-green-200 text-green-800 rounded text-xs">
                                    {{ $sheet['name'] ?? 'Sayfa' }}
                                </span>
                                @endforeach
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Raw JSON -->
                <details class="bg-gray-900 rounded-lg">
                    <summary class="px-4 py-3 text-sm text-gray-300 cursor-pointer hover:text-white">
                        Ham JSON Verisi
                    </summary>
                    <pre class="px-4 pb-4 text-xs text-green-400 overflow-x-auto">{{ json_encode($viewingRules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </details>
            </div>

            <div class="px-6 py-4 border-t border-gray-200">
                <button 
                    wire:click="closeRulesModal"
                    class="w-full px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors"
                >
                    Kapat
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
