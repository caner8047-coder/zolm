<div>
    <!-- Page Header -->
    <div class="mb-6 lg:mb-8">
        <h1 class="text-xl lg:text-2xl font-bold text-gray-900">Operasyon Motoru</h1>
        <p class="text-gray-500 mt-1 text-sm lg:text-base">Sipariş XLS dosyasını yükleyerek operasyon raporlarını oluşturun</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 lg:gap-8">
        <!-- Left Column: Upload & Files -->
        <div class="xl:col-span-2 space-y-4 lg:space-y-6">
            <!-- Import Section -->
            <div class="bg-white rounded-lg border border-gray-200 p-4 lg:p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-4">import</h3>
                
                <!-- Mobile: Stack vertically, Desktop: Horizontal -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 sm:gap-4">
                    <!-- Profile Select -->
                    <div class="relative w-full sm:w-auto">
                        <select wire:model.live="selectedProfileId" class="w-full sm:w-auto appearance-none px-4 py-3 pr-10 border border-gray-300 rounded-lg text-sm bg-white">
                            @foreach($this->profiles as $profile)
                                <option value="{{ $profile->id }}">
                                    {{ $profile->name }}
                                    @if($profile->is_ai_generated) (AI) @endif
                                    @if($profile->is_default) ★ @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>

                    <!-- File Upload -->
                    <label class="flex-1 flex items-center justify-center px-4 lg:px-6 py-4 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-gray-400 transition-colors">
                        <input type="file" wire:model="file" accept=".xlsx,.xls" class="hidden">
                        <div class="flex items-center space-x-2 text-sm text-gray-600">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <span class="truncate">{{ $file ? $file->getClientOriginalName() : 'sipariş xls ↓' }}</span>
                        </div>
                    </label>

                    <button 
                        wire:click="process" 
                        wire:loading.attr="disabled"
                        class="w-full sm:w-auto px-6 py-3 bg-gray-900 text-white font-medium rounded-lg hover:bg-gray-800 disabled:opacity-50 transition-colors"
                    >
                        <span wire:loading.remove wire:target="process">İşle</span>
                        <span wire:loading wire:target="process">İşleniyor...</span>
                    </button>
                </div>

                @error('file')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Selected Profile Info -->
            @if($this->selectedProfile && $this->selectedProfile->is_ai_generated)
            <div class="bg-purple-50 rounded-lg border border-purple-200 p-4">
                <div class="flex items-center space-x-2 mb-2">
                    <svg class="w-5 h-5 text-purple-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span class="font-medium text-purple-900">AI Profili: {{ $this->selectedProfile->name }}</span>
                </div>
                @if($this->selectedProfile->ai_prompt)
                <p class="text-sm text-purple-700">{{ Str::limit($this->selectedProfile->ai_prompt, 150) }}</p>
                @endif
            </div>
            @endif

            <!-- Processing Animation -->
            @if($isProcessing)
            <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                <div class="flex justify-center space-x-1 mb-4">
                    <span class="w-3 h-3 bg-gray-900 rounded-full animate-bounce"></span>
                    <span class="w-3 h-3 bg-gray-900 rounded-full animate-bounce" style="animation-delay: 0.1s"></span>
                    <span class="w-3 h-3 bg-gray-900 rounded-full animate-bounce" style="animation-delay: 0.2s"></span>
                </div>
                <p class="text-gray-600">İşleniyor, lütfen bekleyin...</p>
            </div>
            @endif

            <!-- Message -->
            @if($message)
            <div class="rounded-lg p-4 {{ $messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : ($messageType === 'error' ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-blue-50 border border-blue-200 text-blue-700') }}">
                {{ $message }}
            </div>
            @endif

            <!-- Generated Files -->
            @if(count($generatedFiles) > 0)
            <div class="bg-white rounded-lg border border-gray-200 p-4 lg:p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-4">Oluşturulan Dosyalar</h3>
                <ol class="space-y-3">
                    @foreach($generatedFiles as $index => $gFile)
                    <li class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-start sm:items-center space-x-3">
                            <span class="text-sm text-gray-400 flex-shrink-0">{{ $index + 1 }}.</span>
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900 truncate">{{ $gFile['filename'] }}</p>
                                <p class="text-xs text-gray-500">Çıktı xls dosyası (motorun verdiği çıktı)</p>
                            </div>
                        </div>
                        <a 
                            href="{{ route('download', $gFile['id']) }}" 
                            class="inline-flex items-center justify-center px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors flex-shrink-0"
                        >
                            xls dışarı aktar
                        </a>
                    </li>
                    @endforeach
                </ol>
            </div>
            @endif
        </div>

        <!-- Right Column: Actions -->
        <div class="space-y-4 lg:space-y-6">
            <!-- New Profile Button -->
            <a href="{{ route('profile.wizard') }}" class="block w-full px-4 py-3 bg-purple-600 text-white font-medium rounded-lg hover:bg-purple-700 transition-colors text-center">
                ✨ AI ile Yeni Profil
            </a>
            <p class="text-xs text-gray-400 text-center -mt-2">örnek dosya yükle, AI kuralları oluştursun</p>

            @if(count($generatedFiles) > 0)
            <!-- Quick Actions -->
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-medium text-gray-700 mb-3">geçmiş kaydedilen raporları seçip ekrana getir</h4>
                <a href="{{ route('reports') }}" class="block w-full px-4 py-2 text-sm text-center border border-gray-300 rounded-lg hover:bg-gray-50">
                    gün bazında rapor seç
                </a>
            </div>

            <!-- Bulk Download -->
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <button 
                    wire:click="downloadAll"
                    class="w-full px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                >
                    ↓ toplu indir (zip)
                </button>
                <p class="text-xs text-gray-400 mt-2 text-center">tüm xls dosyalarını zipleyip indir</p>
            </div>

            <!-- Save All -->
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <button 
                    wire:click="saveAllToHistory"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50 cursor-not-allowed"
                    wire:target="saveAllToHistory"
                    class="w-full px-4 py-3 bg-gray-900 text-white font-medium rounded-lg hover:bg-gray-800 transition-colors disabled:opacity-50"
                    {{ $isSaving ? 'disabled' : '' }}
                >
                    <span wire:loading.remove wire:target="saveAllToHistory">
                        @if($isSaving)
                            <span class="inline-flex items-center justify-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Kaydediliyor...
                            </span>
                        @elseif($saveMessage)
                            {{ $saveMessage }}
                        @else
                            Tüm motor çıktılarını kaydet
                        @endif
                    </span>
                    <span wire:loading wire:target="saveAllToHistory" class="inline-flex items-center justify-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Kaydediliyor...
                    </span>
                </button>
                <p class="text-xs text-gray-400 mt-2 text-center">motorun verdiği çıktıyı tarihli bir şekilde kaydet</p>
            </div>
            @endif

            <!-- AI Chat Section -->
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <h4 class="font-medium text-gray-900 mb-2">ai chat</h4>
                <p class="text-xs text-gray-500 mb-4">operasyon listesi çıktılarını E-ticaret operasyon sorumlusu olarak analiz edip yorumla ve aksiyon planı oluştur</p>
                <a href="{{ route('ai-chat') }}" class="block w-full px-4 py-2 text-sm text-center bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                    AI Chat'e Git →
                </a>
            </div>
        </div>
    </div>
</div>
