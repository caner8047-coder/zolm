<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Yeni Profil Oluştur</h1>
        <p class="text-gray-500 mt-1">AI destekli profil oluşturma sihirbazı</p>
    </div>

    <!-- Progress Steps -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            @foreach(range(1, $totalSteps) as $step)
                <div class="flex items-center {{ $step < $totalSteps ? 'flex-1' : '' }}">
                    <button 
                        wire:click="goToStep({{ $step }})"
                        class="w-10 h-10 rounded-full flex items-center justify-center font-medium transition-colors
                            {{ $step < $currentStep ? 'bg-green-500 text-white cursor-pointer' : '' }}
                            {{ $step === $currentStep ? 'bg-gray-900 text-white' : '' }}
                            {{ $step > $currentStep ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : '' }}"
                        {{ $step > $currentStep ? 'disabled' : '' }}
                    >
                        @if($step < $currentStep)
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        @else
                            {{ $step }}
                        @endif
                    </button>
                    @if($step < $totalSteps)
                        <div class="flex-1 h-1 mx-4 {{ $step < $currentStep ? 'bg-green-500' : 'bg-gray-200' }}"></div>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="flex justify-between mt-2">
            <span class="text-xs text-gray-500">Temel Bilgiler</span>
            <span class="text-xs text-gray-500">Girdi Dosyası</span>
            <span class="text-xs text-gray-500">Çıktı Tanımı</span>
            <span class="text-xs text-gray-500">AI Analiz</span>
        </div>
    </div>

    <!-- Step Content -->
    <div class="bg-white rounded-lg border border-gray-200 p-8">
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-gray-900">{{ $this->stepTitle }}</h2>
            <p class="text-sm text-gray-500">{{ $this->stepDescription }}</p>
        </div>

        <!-- Step 1: Temel Bilgiler -->
        @if($currentStep === 1)
        <div class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Profil Adı *</label>
                <input 
                    type="text" 
                    wire:model="name"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900"
                    placeholder="Örn: Günlük Üretim Raporu"
                >
                @error('name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Profil Türü *</label>
                <div class="grid grid-cols-2 gap-4">
                    <label class="relative flex items-center p-4 border rounded-lg cursor-pointer transition-colors
                        {{ $type === 'production' ? 'border-gray-900 bg-gray-50' : 'border-gray-300 hover:border-gray-400' }}">
                        <input type="radio" wire:model="type" value="production" class="sr-only">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900">Üretim</div>
                                <div class="text-sm text-gray-500">Üretim raporları için</div>
                            </div>
                        </div>
                        @if($type === 'production')
                            <div class="absolute top-2 right-2 w-5 h-5 bg-gray-900 rounded-full flex items-center justify-center">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                        @endif
                    </label>

                    <label class="relative flex items-center p-4 border rounded-lg cursor-pointer transition-colors
                        {{ $type === 'operation' ? 'border-gray-900 bg-gray-50' : 'border-gray-300 hover:border-gray-400' }}">
                        <input type="radio" wire:model="type" value="operation" class="sr-only">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900">Operasyon</div>
                                <div class="text-sm text-gray-500">Operasyon raporları için</div>
                            </div>
                        </div>
                        @if($type === 'operation')
                            <div class="absolute top-2 right-2 w-5 h-5 bg-gray-900 rounded-full flex items-center justify-center">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                        @endif
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Açıklama (Opsiyonel)</label>
                <textarea 
                    wire:model="description"
                    rows="3"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900"
                    placeholder="Bu profil ne için kullanılacak?"
                ></textarea>
            </div>
        </div>
        @endif

        <!-- Step 2: Örnek Girdi -->
        @if($currentStep === 2)
        <div class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Örnek Girdi Dosyası *</label>
                <label class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-gray-400 transition-colors
                    {{ $sampleInputFile ? 'border-green-500 bg-green-50' : '' }}">
                    <input type="file" wire:model="sampleInputFile" accept=".xlsx,.xls" class="hidden">
                    @if($sampleInputFile)
                        <svg class="w-12 h-12 text-green-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-sm font-medium text-gray-900">{{ $sampleInputFile->getClientOriginalName() }}</span>
                        <span class="text-xs text-gray-500 mt-1">Dosyayı değiştirmek için tıklayın</span>
                    @else
                        <svg class="w-12 h-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <span class="text-sm text-gray-600">XLS veya XLSX dosyası yükleyin</span>
                        <span class="text-xs text-gray-400 mt-1">Maksimum 10MB</span>
                    @endif
                </label>
                @error('sampleInputFile') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <!-- Analiz Sonucu -->
            @if($inputAnalyzed && !empty($inputStructure))
            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="text-sm font-medium text-gray-900 mb-3">📊 Dosya Analizi</h4>
                
                @if(isset($inputStructure['sheets']))
                <div class="space-y-3">
                    @foreach($inputStructure['sheets'] as $sheet)
                    <div class="bg-white rounded border border-gray-200 p-3">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-medium text-gray-900">{{ $sheet['name'] }}</span>
                            <span class="text-xs text-gray-500">{{ $sheet['row_count'] ?? '?' }} satır</span>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            @foreach(($sheet['columns'] ?? []) as $column)
                            <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-700 rounded">{{ $column }}</span>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            @endif
        </div>
        @endif

        <!-- Step 3: Çıktı Tanımı -->
        @if($currentStep === 3)
        <div class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">İstediğiniz Çıktıyı Açıklayın *</label>
                <textarea 
                    wire:model="aiPrompt"
                    rows="6"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900"
                    placeholder="Örnek:
- Renk Etiketi sütununa göre grupla (BERJER, PUF, KÖŞE VE KANEPE)
- Her grup için ayrı dosya oluştur
- Her dosyada ürün bazlı toplam sayfası olsun
- Detay sayfasında tüm siparişler tarihe göre sıralansın
- Kargoya son teslim tarihi bugün olanları işaretle"
                ></textarea>
                @error('aiPrompt') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-500 mt-2">
                    Ne kadar detaylı açıklarsanız, AI o kadar doğru kurallar üretir.
                </p>
            </div>

            <div class="border-t border-gray-200 pt-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Örnek Çıktı Dosyası (Opsiyonel)</label>
                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-gray-400 transition-colors
                    {{ $sampleOutputFile ? 'border-green-500 bg-green-50' : '' }}">
                    <input type="file" wire:model="sampleOutputFile" accept=".xlsx,.xls" class="hidden">
                    @if($sampleOutputFile)
                        <svg class="w-8 h-8 text-green-500 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-sm font-medium text-gray-900">{{ $sampleOutputFile->getClientOriginalName() }}</span>
                    @else
                        <svg class="w-8 h-8 text-gray-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <span class="text-xs text-gray-600">Örnek çıktı dosyası yükleyin (opsiyonel)</span>
                    @endif
                </label>
                <p class="text-xs text-gray-500 mt-2">
                    Örnek çıktı yüklerseniz AI daha doğru analiz yapabilir.
                </p>
            </div>
        </div>
        @endif

        <!-- Step 4: AI Analiz -->
        @if($currentStep === 4)
        <div class="space-y-6">
            @if($isAnalyzing)
            <!-- Analyzing State -->
            <div class="text-center py-12">
                <div class="flex justify-center space-x-2 mb-4">
                    <span class="w-3 h-3 bg-gray-900 rounded-full animate-bounce"></span>
                    <span class="w-3 h-3 bg-gray-900 rounded-full animate-bounce" style="animation-delay: 0.1s"></span>
                    <span class="w-3 h-3 bg-gray-900 rounded-full animate-bounce" style="animation-delay: 0.2s"></span>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">AI Analiz Ediyor...</h3>
                <p class="text-gray-500">Dosyalarınız ve açıklamanız inceleniyor, dönüşüm kuralları oluşturuluyor.</p>
            </div>
            @elseif($analysisError)
            <!-- Error State -->
            <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                <div class="flex items-start">
                    <svg class="w-6 h-6 text-red-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <h4 class="font-medium text-red-800">Analiz Hatası</h4>
                        <p class="text-sm text-red-700 mt-1">{{ $analysisError }}</p>
                    </div>
                </div>
                <button 
                    wire:click="retryAnalysis"
                    class="mt-4 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                >
                    Tekrar Dene
                </button>
            </div>
            @elseif($analysisComplete)
            <!-- Success State -->
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="font-medium text-green-800">AI kuralları başarıyla oluşturdu!</span>
                </div>
            </div>

            <!-- Generated Rules Preview -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="text-sm font-medium text-gray-900 mb-3">📋 Oluşturulan Kurallar</h4>
                
                @if(isset($generatedRules['transformations']))
                <div class="space-y-2 mb-4">
                    <h5 class="text-xs font-medium text-gray-500 uppercase">Dönüşümler</h5>
                    @foreach($generatedRules['transformations'] as $transform)
                    <div class="bg-white rounded border border-gray-200 p-3 text-sm">
                        <span class="font-medium text-gray-900">{{ $transform['type'] ?? 'işlem' }}</span>
                        @if(isset($transform['description']))
                        <p class="text-gray-600 text-xs mt-1">{{ $transform['description'] }}</p>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif

                @if(isset($generatedRules['outputs']))
                <div class="space-y-2">
                    <h5 class="text-xs font-medium text-gray-500 uppercase">Çıktı Dosyaları</h5>
                    @foreach($generatedRules['outputs'] as $output)
                    <div class="bg-white rounded border border-gray-200 p-3 text-sm">
                        <span class="font-medium text-gray-900">{{ $output['filename_pattern'] ?? 'dosya.xlsx' }}</span>
                        @if(isset($output['sheets']))
                        <div class="flex flex-wrap gap-1 mt-2">
                            @foreach($output['sheets'] as $sheet)
                            <span class="px-2 py-0.5 text-xs bg-blue-100 text-blue-700 rounded">{{ $sheet['name'] }}</span>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            <!-- Raw JSON (Collapsible) -->
            <details class="bg-gray-900 rounded-lg">
                <summary class="px-4 py-3 text-sm text-gray-300 cursor-pointer hover:text-white">
                    Ham JSON Çıktısı
                </summary>
                <pre class="px-4 pb-4 text-xs text-green-400 overflow-x-auto">{{ json_encode($generatedRules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </details>
            @endif
        </div>
        @endif
    </div>

    <!-- Navigation Buttons -->
    <div class="flex justify-between mt-6">
        <button 
            wire:click="previousStep"
            class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors
                {{ $currentStep === 1 ? 'invisible' : '' }}"
        >
            ← Geri
        </button>

        <div class="flex space-x-3">
            <a href="{{ route('profiles') }}" class="px-6 py-3 text-gray-600 hover:text-gray-900">
                İptal
            </a>

            @if($currentStep < $totalSteps)
            <button 
                wire:click="nextStep"
                wire:loading.attr="disabled"
                class="px-6 py-3 bg-gray-900 text-white font-medium rounded-lg hover:bg-gray-800 disabled:opacity-50 transition-colors"
            >
                <span wire:loading.remove wire:target="nextStep">İleri →</span>
                <span wire:loading wire:target="nextStep">İşleniyor...</span>
            </button>
            @else
            <button 
                wire:click="saveProfile"
                wire:loading.attr="disabled"
                class="px-6 py-3 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 disabled:opacity-50 transition-colors
                    {{ !$analysisComplete ? 'opacity-50 cursor-not-allowed' : '' }}"
                {{ !$analysisComplete ? 'disabled' : '' }}
            >
                <span wire:loading.remove wire:target="saveProfile">✓ Profili Kaydet</span>
                <span wire:loading wire:target="saveProfile">Kaydediliyor...</span>
            </button>
            @endif
        </div>
    </div>
</div>
