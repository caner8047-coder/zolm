<div>
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">
            {{ $motorType === 'production' ? 'Üretim Motoru' : 'Operasyon Motoru' }}
        </h1>
        <p class="text-gray-500 mt-1">Sipariş XLS dosyasını yükleyerek raporları oluşturun</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column: Upload & Files -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Import Section -->
            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-4">import</h3>
                
                <div class="flex items-center space-x-4">
                    <!-- Profile Select -->
                    <div class="relative">
                        <select wire:model.live="selectedProfileId" class="appearance-none px-4 py-2 pr-8 border border-gray-300 rounded-lg text-sm bg-white">
                            @foreach($this->profiles as $profile)
                                <option value="{{ $profile->id }}">
                                    {{ $profile->name }}
                                    @if($profile->is_ai_generated) (AI) @endif
                                    @if($profile->is_default) ★ @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>

                    <!-- File Upload -->
                    <label class="flex-1 flex items-center justify-center px-6 py-4 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-gray-400 transition-colors">
                        <input type="file" wire:model="file" accept=".xlsx,.xls" class="hidden">
                        <div class="flex items-center space-x-2 text-sm text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <span>{{ $file ? $file->getClientOriginalName() : 'sipariş xls ↓' }}</span>
                        </div>
                    </label>

                    <button 
                        wire:click="process" 
                        wire:loading.attr="disabled"
                        class="px-6 py-3 bg-gray-900 text-white font-medium rounded-lg hover:bg-gray-800 disabled:opacity-50 transition-colors"
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
            @if($this->selectedProfile)
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm font-medium text-gray-900">{{ $this->selectedProfile->name }}</span>
                        @if($this->selectedProfile->is_ai_generated)
                            <span class="ml-2 px-2 py-0.5 text-xs bg-purple-100 text-purple-700 rounded">AI Profili</span>
                        @endif
                    </div>
                    @if($this->selectedProfile->ai_prompt)
                    <details class="text-xs text-gray-500">
                        <summary class="cursor-pointer hover:text-gray-700">Açıklama</summary>
                        <p class="mt-2 p-2 bg-white rounded border border-gray-200">{{ $this->selectedProfile->ai_prompt }}</p>
                    </details>
                    @endif
                </div>
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
                <p class="text-gray-600">motor çalışıyor... (animasyon)</p>
            </div>
            @endif

            <!-- Message -->
            @if($message)
            <div class="p-4 rounded-lg {{ $messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : ($messageType === 'error' ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-blue-50 border border-blue-200 text-blue-700') }}">
                {{ $message }}
            </div>
            @endif

            <!-- Generated Files -->
            @if(count($generatedFiles) > 0)
            <div class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-200">
                @foreach($generatedFiles as $index => $file)
                <div class="flex items-center justify-between p-4">
                    <div class="flex items-center space-x-3">
                        <span class="text-gray-400">{{ $index + 1 }}.</span>
                        <div>
                            <p class="font-medium text-gray-900">{{ $file['filename'] }}</p>
                            <p class="text-sm text-gray-500">Çıktı xls dosyası</p>
                        </div>
                    </div>
                    <button 
                        wire:click="downloadFile({{ $file['id'] }})"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        xls dışarı aktar
                    </button>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        <!-- Right Column: Actions -->
        <div class="space-y-6">
            <!-- New Profile Button -->
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <a href="{{ route('profile.wizard') }}" class="flex items-center justify-center w-full px-4 py-3 bg-purple-600 text-white font-medium rounded-lg hover:bg-purple-700 transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    AI ile Yeni Profil Oluştur
                </a>
                <p class="text-xs text-gray-400 mt-2 text-center">Örnek dosya yükle, AI kuralları otomatik oluştursun</p>
            </div>

            <!-- History Button -->
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <p class="text-xs text-gray-500 mb-2">geçmiş kaydedilen raporları seçip ekrana getir</p>
                <a href="{{ route('reports') }}" class="block w-full px-4 py-2 text-sm text-center border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    gün bazında rapor seç
                </a>
            </div>

            @if(count($generatedFiles) > 0)
            <!-- Save All -->
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <button class="w-full px-4 py-3 bg-gray-900 text-white font-medium rounded-lg hover:bg-gray-800 transition-colors">
                    Tüm motor çıktılarını kaydet
                </button>
                <p class="text-xs text-gray-400 mt-2 text-center">motorun verdiği çıktıyı tarihli bir şekilde kaydet</p>
            </div>
            @endif

            <!-- AI Chat Section -->
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <h4 class="font-medium text-gray-900 mb-2">ai chat</h4>
                <p class="text-xs text-gray-500 mb-4">çıktıları analiz edip önerilerde bulun</p>
                <a href="{{ route('ai-chat') }}" class="block w-full px-4 py-2 text-sm text-center bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                    AI Chat'e Git →
                </a>
            </div>
        </div>
    </div>
</div>
