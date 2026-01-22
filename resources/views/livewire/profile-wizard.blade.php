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
                    <div wire:click="$set('type', 'production')" class="relative flex items-center p-4 border rounded-lg cursor-pointer transition-colors
                        {{ $type === 'production' ? 'border-gray-900 bg-gray-50' : 'border-gray-300 hover:border-gray-400' }}">
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
                    </div>

                    <div wire:click="$set('type', 'operation')" class="relative flex items-center p-4 border rounded-lg cursor-pointer transition-colors
                        {{ $type === 'operation' ? 'border-gray-900 bg-gray-50' : 'border-gray-300 hover:border-gray-400' }}">
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
                    </div>
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

        <!-- Step 2: Örnek Girdi (Çoklu Dosya) -->
        @if($currentStep === 2)
        <div class="space-y-6">
            <!-- Dosya Listesi -->
            @if(count($sampleInputFiles) > 0)
            <div class="space-y-3">
                <label class="block text-sm font-medium text-gray-700">Eklenen Girdi Dosyaları ({{ count($sampleInputFiles) }})</label>
                @foreach($sampleInputFiles as $index => $file)
                <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <span class="font-medium text-gray-900">{{ $file['name'] }}</span>
                            @if(isset($file['structure']['sheets']))
                            <span class="text-xs text-gray-500 ml-2">({{ count($file['structure']['sheets']) }} sayfa)</span>
                            @endif
                        </div>
                    </div>
                    <button 
                        wire:click="removeInputFile({{ $index }})"
                        class="p-1 text-red-500 hover:bg-red-100 rounded transition-colors"
                        title="Dosyayı Kaldır"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
                @endforeach
            </div>
            @endif

            <!-- Dosya Ekleme -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    {{ count($sampleInputFiles) > 0 ? 'Başka Dosya Ekle' : 'Örnek Girdi Dosyası *' }}
                </label>
                <div class="flex gap-3">
                    <label class="flex-1 flex flex-col items-center justify-center h-32 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-gray-400 transition-colors
                        {{ $tempInputFile ? 'border-blue-500 bg-blue-50' : '' }}">
                        <input type="file" wire:model="tempInputFile" accept=".xlsx,.xls" class="hidden">
                        @if($tempInputFile)
                            <svg class="w-8 h-8 text-blue-500 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-900">{{ $tempInputFile->getClientOriginalName() }}</span>
                            <span class="text-xs text-blue-600 mt-1">Eklemek için butona tıklayın</span>
                        @else
                            <svg class="w-8 h-8 text-gray-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <span class="text-sm text-gray-600">XLS veya XLSX dosyası seçin</span>
                            <span class="text-xs text-gray-400 mt-1">Maksimum 10MB</span>
                        @endif
                    </label>
                    
                    @if($tempInputFile)
                    <button 
                        wire:click="addInputFile"
                        class="px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors h-32 flex flex-col items-center justify-center"
                    >
                        <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        <span class="text-sm">Ekle</span>
                    </button>
                    @endif
                </div>
                @error('tempInputFile') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                @error('sampleInputFiles') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <!-- Analiz Sonucu -->
            @if($inputAnalyzed && !empty($inputStructure['sheets']))
            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="text-sm font-medium text-gray-900 mb-3">📊 Toplam Dosya Analizi ({{ count($inputStructure['sheets']) }} sayfa)</h4>
                
                <div class="space-y-3 max-h-64 overflow-y-auto">
                    @foreach($inputStructure['sheets'] as $sheet)
                    <div class="bg-white rounded border border-gray-200 p-3">
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <span class="font-medium text-gray-900">{{ $sheet['name'] }}</span>
                                @if(isset($sheet['file']))
                                <span class="text-xs text-gray-400 ml-2">({{ $sheet['file'] }})</span>
                                @endif
                            </div>
                            <span class="text-xs text-gray-500">{{ $sheet['row_count'] ?? '?' }} satır</span>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            @foreach(array_slice($sheet['columns'] ?? [], 0, 10) as $column)
                            <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-700 rounded">{{ $column }}</span>
                            @endforeach
                            @if(count($sheet['columns'] ?? []) > 10)
                            <span class="px-2 py-0.5 text-xs bg-gray-200 text-gray-600 rounded">+{{ count($sheet['columns']) - 10 }} daha</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @endif

        <!-- Step 3: Çıktı Tanımı (Çoklu Dosya) -->
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
                <label class="block text-sm font-medium text-gray-700 mb-3">Örnek Çıktı Dosyaları (Opsiyonel)</label>
                
                <!-- Çıktı Dosya Listesi -->
                @if(count($sampleOutputFiles) > 0)
                <div class="space-y-2 mb-4">
                    @foreach($sampleOutputFiles as $index => $file)
                    <div class="flex items-center justify-between p-2 bg-purple-50 border border-purple-200 rounded-lg">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 text-purple-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-900">{{ $file['name'] }}</span>
                            @if(isset($file['structure']['sheets']))
                            <span class="text-xs text-gray-500 ml-2">({{ count($file['structure']['sheets']) }} sayfa)</span>
                            @endif
                        </div>
                        <button 
                            wire:click="removeOutputFile({{ $index }})"
                            class="p-1 text-red-500 hover:bg-red-100 rounded transition-colors"
                            title="Dosyayı Kaldır"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    @endforeach
                </div>
                @endif

                <!-- Çıktı Dosya Ekleme -->
                <div class="flex gap-3">
                    <label class="flex-1 flex flex-col items-center justify-center h-24 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-gray-400 transition-colors
                        {{ $tempOutputFile ? 'border-purple-500 bg-purple-50' : '' }}">
                        <input type="file" wire:model="tempOutputFile" accept=".xlsx,.xls" class="hidden">
                        @if($tempOutputFile)
                            <svg class="w-6 h-6 text-purple-500 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-900">{{ $tempOutputFile->getClientOriginalName() }}</span>
                        @else
                            <svg class="w-6 h-6 text-gray-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            <span class="text-xs text-gray-600">Örnek çıktı ekle</span>
                        @endif
                    </label>
                    
                    @if($tempOutputFile)
                    <button 
                        wire:click="addOutputFile"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors h-24 flex flex-col items-center justify-center"
                    >
                        <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        <span class="text-sm">Ekle</span>
                    </button>
                    @endif
                </div>
                @error('tempOutputFile') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                
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

            <!-- JSON Düzenleme Butonu -->
            <div class="flex items-center justify-between mt-4">
                <button 
                    wire:click="openJsonEditor"
                    class="flex items-center space-x-2 px-4 py-2 text-sm font-medium text-purple-600 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <span>JSON'u Düzenle</span>
                </button>
                
                @if(session('json-saved'))
                <span class="text-sm text-green-600 flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Değişiklikler kaydedildi
                </span>
                @endif
            </div>

            <!-- Raw JSON (Collapsible) -->
            <details class="bg-gray-900 rounded-lg mt-4">
                <summary class="px-4 py-3 text-sm text-gray-300 cursor-pointer hover:text-white">
                    Ham JSON Çıktısı (Sadece Görüntüle)
                </summary>
                <pre class="px-4 pb-4 text-xs text-green-400 overflow-x-auto max-h-64">{{ json_encode($generatedRules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </details>
            @endif
        </div>
        @endif
    </div>

    <!-- JSON Editor Modal -->
    @if($showJsonEditor)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">JSON Kurallarını Düzenle</h2>
                    <p class="text-sm text-gray-500">AI'ın oluşturduğu kuralları manuel olarak düzenleyebilirsiniz</p>
                </div>
                <button wire:click="closeJsonEditor" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Error Message -->
            @if($jsonEditorError)
            <div class="mx-6 mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ $jsonEditorError }}
                </div>
            </div>
            @endif

            <!-- Editor -->
            <div class="flex-1 p-6 overflow-hidden">
                <textarea 
                    wire:model="jsonEditorContent"
                    class="w-full h-full min-h-[400px] px-4 py-3 font-mono text-sm bg-gray-900 text-green-400 border border-gray-700 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                    spellcheck="false"
                ></textarea>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <button 
                        wire:click="formatJson"
                        class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900 border border-gray-300 rounded-lg hover:bg-gray-100"
                    >
                        <span class="flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/>
                            </svg>
                            Formatla
                        </span>
                    </button>
                    <span class="text-xs text-gray-400">Ctrl+Shift+F</span>
                </div>
                
                <div class="flex items-center space-x-3">
                    <button 
                        wire:click="closeJsonEditor"
                        class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                    >
                        İptal
                    </button>
                    <button 
                        wire:click="saveJsonEditorChanges"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700"
                    >
                        <span class="flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Değişiklikleri Kaydet
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

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
                    {{ (!$analysisComplete || $isSaving) ? 'opacity-50 cursor-not-allowed' : '' }}"
                {{ (!$analysisComplete || $isSaving) ? 'disabled' : '' }}
            >
                <span wire:loading.remove wire:target="saveProfile">
                    @if($isSaving)
                        Kaydediliyor...
                    @else
                        ✓ Profili Kaydet
                    @endif
                </span>
                <span wire:loading wire:target="saveProfile">Kaydediliyor...</span>
            </button>
            @endif
        </div>
    </div>
</div>
