<div class="space-y-6 overflow-x-hidden">
    
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-gray-900">📊 Kâr Motoru</h1>
            <p class="text-sm lg:text-base text-gray-500 mt-1">Tarife optimizasyonu ile net kârınızı maksimize edin</p>
        </div>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($message): ?>
        <div class="rounded-lg p-4 <?php echo e($messageType === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 
            ($messageType === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 
            'bg-blue-50 text-blue-800 border border-blue-200')); ?>">
            <?php echo e($message); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="border-b border-gray-200">
        <nav class="flex gap-4 sm:gap-8">
            <button wire:click="switchTab('analyze')"
                class="py-3 px-1 text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                    <?php echo e($activeTab === 'analyze' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'); ?>">
                🔬 Yeni Analiz
            </button>
            <button wire:click="switchTab('history')"
                class="py-3 px-1 text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                    <?php echo e($activeTab === 'history' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'); ?>">
                📋 Geçmiş Raporlar
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->reports->count() > 0): ?>
                    <span class="ml-1.5 px-2 py-0.5 text-xs bg-gray-200 text-gray-700 rounded-full"><?php echo e($this->reports->count()); ?></span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </button>
        </nav>
    </div>

    
    
    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeTab === 'analyze'): ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step === 1): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 lg:gap-6">
                
                <div class="bg-gradient-to-br from-blue-50 to-indigo-100 rounded-xl p-4 lg:p-6 border border-blue-200">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-lg bg-white shadow flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-medium text-gray-900">Maliyet Veritabanı</h3>
                            <p class="text-sm text-gray-500 mt-1">Üretim ve kargo maliyetleriniz</p>
                            <div class="mt-3">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->costCount > 0): ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                        ✓ <?php echo e($this->costCount); ?> ürünün maliyeti kayıtlı
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                        ⚠ Henüz maliyet yüklenmedi
                                    </span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <div class="mt-4">
                                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Maliyet dosyası yükle</label>
                                <input 
                                    type="file" 
                                    wire:model="costFile"
                                    accept=".xlsx,.xls"
                                    class="block w-full text-sm text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200"
                                >
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($costFile): ?>
                                    <span class="text-xs text-green-600 mt-1 block">✓ <?php echo e($costFile->getClientOriginalName()); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['costFile'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-xs text-red-500"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($costFile): ?>
                                <button 
                                    wire:click="importCosts"
                                    wire:loading.attr="disabled"
                                    class="mt-3 w-full sm:w-auto px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition flex justify-center items-center gap-2 min-h-[44px]"
                                >
                                    <span wire:loading.remove wire:target="importCosts">Maliyetleri Güncelle</span>
                                    <span wire:loading wire:target="importCosts">
                                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Yükleniyor...
                                    </span>
                                </button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                </div>

                
                <div class="bg-gradient-to-br from-emerald-50 to-green-100 rounded-xl p-4 lg:p-6 border border-emerald-200">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-lg bg-white shadow flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-medium text-gray-900">Haftalık Tarife Analizi</h3>
                            <p class="text-sm text-gray-500 mt-1">Trendyol komisyon tarife Excel dosyasını yükleyin</p>
                            <div class="mt-4">
                                <input 
                                    type="file" 
                                    wire:model="tariffFile"
                                    accept=".xlsx,.xls"
                                    class="block w-full text-sm text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-emerald-100 file:text-emerald-700 hover:file:bg-emerald-200"
                                >
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tariffFile): ?>
                                    <span class="text-xs text-green-600 mt-1 block">✓ <?php echo e($tariffFile->getClientOriginalName()); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['tariffFile'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-xs text-red-500"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <div class="mt-3">
                                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Rapor Adı (Opsiyonel)</label>
                                <input 
                                    type="text" 
                                    wire:model="reportName"
                                    placeholder="Örn: 11 Şubat 2026 Analizi"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 text-base sm:text-sm min-h-[44px]"
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->costCount === 0): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm text-yellow-700">
                    <div class="flex items-start gap-2">
                        <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        <span>Maliyet veritabanı boş. Önce sol panelden maliyet dosyanızı (fiyatli_maliyet_eklenmis.xlsx) yükleyin. Aksi halde motor maliyetleri 0 kabul ederek çalışır.</span>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tariffFile): ?>
                <div class="flex justify-center">
                    <button 
                        wire:click="analyze"
                        wire:loading.attr="disabled"
                        class="w-full sm:w-auto px-8 py-4 bg-gradient-to-r from-emerald-600 to-green-600 text-white font-bold rounded-xl hover:from-emerald-700 hover:to-green-700 transition shadow-lg disabled:opacity-50 flex justify-center items-center gap-3 text-lg min-h-[56px]"
                    >
                        <span wire:loading.remove wire:target="analyze">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </span>
                        <span wire:loading wire:target="analyze">
                            <svg class="animate-spin h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                        <span wire:loading.remove wire:target="analyze">Motoru Çalıştır</span>
                        <span wire:loading wire:target="analyze">Analiz ediliyor...</span>
                    </button>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step === 2): ?>
            <div class="flex flex-col items-center justify-center py-12 lg:py-20">
                <div class="relative">
                    <div class="w-24 h-24 rounded-full border-4 border-emerald-200 border-t-emerald-600 animate-spin"></div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <svg class="w-10 h-10 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                </div>
                <h2 class="mt-6 text-xl lg:text-2xl font-bold text-gray-900">Kâr Motoru Çalışıyor</h2>
                <div class="mt-4 space-y-2 text-sm lg:text-base text-gray-500 text-center" x-data="{ step: 0 }" x-init="setInterval(() => step = (step + 1) % 4, 1500)">
                    <p x-show="step === 0" x-transition>📊 Tarife verileri okunuyor...</p>
                    <p x-show="step === 1" x-transition>🔗 Maliyetler eşleştiriliyor...</p>
                    <p x-show="step === 2" x-transition>🔍 Komisyon tuzakları taranıyor...</p>
                    <p x-show="step === 3" x-transition>💰 Kârlılık hesaplanıyor...</p>
                </div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step === 3 && $this->activeReport): ?>
            <?php $report = $this->activeReport; ?>



            
            <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
                <button wire:click="resetAnalysis"
                    class="w-full sm:w-auto px-4 py-3 sm:py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 flex justify-center items-center gap-2 min-h-[44px]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Yeni Analiz
                </button>
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($selectedItems) > 0): ?>
                        <span class="text-sm text-gray-500"><?php echo e(count($selectedItems)); ?> ürün seçili</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <button wire:click="exportSelected"
                        class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex justify-center items-center gap-2 min-h-[44px]">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Düzeltme Dosyasını İndir
                    </button>
                </div>
            </div>

            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
                <div class="bg-white rounded-xl p-3 sm:p-4 border border-gray-200 shadow-sm">
                    <div class="text-gray-500 text-xs sm:text-sm">Analiz Edilen</div>
                    <div class="text-xl sm:text-2xl font-bold text-gray-900"><?php echo e(number_format($report->total_products)); ?></div>
                    <div class="text-xs text-gray-400 mt-1">ürün</div>
                </div>
                <div class="bg-white rounded-xl p-3 sm:p-4 border border-emerald-200 shadow-sm">
                    <div class="text-emerald-500 text-xs sm:text-sm">Fırsat Bulunan</div>
                    <div class="text-xl sm:text-2xl font-bold text-emerald-600"><?php echo e(number_format($report->opportunity_count)); ?></div>
                    <div class="text-xs text-emerald-400 mt-1">ürün</div>
                </div>
                <div class="bg-white rounded-xl p-3 sm:p-4 border border-green-200 shadow-sm">
                    <div class="text-green-500 text-xs sm:text-sm">Toplam Ek Kâr</div>
                    <div class="text-xl sm:text-2xl font-bold text-green-600">+<?php echo e(number_format($report->total_extra_profit, 2)); ?> ₺</div>
                    <div class="text-xs text-green-400 mt-1">birim başına potansiyel</div>
                </div>
                <div class="bg-white rounded-xl p-3 sm:p-4 border border-yellow-200 shadow-sm">
                    <div class="text-yellow-500 text-xs sm:text-sm">Eşleşmeyen</div>
                    <div class="text-xl sm:text-2xl font-bold text-yellow-600"><?php echo e(number_format($report->unmatched_count)); ?></div>
                    <div class="text-xs text-yellow-400 mt-1">maliyetsiz ürün</div>
                </div>
            </div>

            
            <div class="flex flex-col sm:flex-row items-center gap-3 bg-gray-50 rounded-lg p-3 lg:p-4">
                <button wire:click="selectAllOpportunities" class="w-full sm:w-auto px-3 py-2 text-sm bg-emerald-100 text-emerald-700 rounded-lg hover:bg-emerald-200 min-h-[44px]">
                    ✓ Tüm Fırsatları Seç
                </button>
                <button wire:click="deselectAll" class="w-full sm:w-auto px-3 py-2 text-sm bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 min-h-[44px]">
                    Seçimi Temizle
                </button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($selectedItems) > 0): ?>
                    <span class="text-sm font-medium text-orange-600">
                        🛒 <?php echo e(count($selectedItems)); ?> ürün seçildi
                    </span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <div class="text-sm text-gray-500 sm:ml-auto">
                    <?php echo e($report->original_filename); ?> · <?php echo e($report->created_at->format('d.m.Y H:i')); ?>

                </div>
            </div>

            
            
            
            <div class="bg-white rounded-lg border border-gray-200 p-3 mb-3" x-data="{ showCustom: false }">
                
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span class="text-xs font-semibold text-gray-600 mr-1">📋 Hedef Tarife:</span>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [0 => '1. Tarife', 1 => '2. Tarife', 2 => '3. Tarife', 3 => '4. Tarife']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tIdx => $tName): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <button wire:click="setProfitabilityFilter(<?php echo e($tIdx); ?>, <?php echo e($profitabilityMin ?? 0); ?>, <?php echo e($profitabilityMax ?? 999); ?>)"
                            class="px-3 py-1.5 text-[11px] font-semibold rounded-lg border-2 transition-all <?php echo e($profitabilityTariffIndex === $tIdx ? ($tIdx === 0 ? 'bg-orange-500 text-white border-orange-500 shadow-md' : 'bg-indigo-600 text-white border-indigo-600 shadow-md') : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-300 hover:text-indigo-600'); ?>">
                            <?php echo e($tName); ?>

                        </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profitabilityTariffIndex !== null): ?>
                        <button wire:click="clearProfitabilityFilter" class="px-2 py-1 text-[11px] text-red-500 hover:text-red-700 font-medium ml-1">
                            ✕ Temizle
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profitabilityTariffIndex !== null): ?>
                    <div class="flex flex-wrap items-center gap-1.5 pt-2 border-t border-gray-100">
                        <span class="text-xs font-semibold text-gray-500 mr-1">🎯 Karlılık %:</span>
                        <?php
                            $ranges = [[0,5],[5,10],[10,15],[15,20],[20,25],[25,30],[30,null]];
                        ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $ranges; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$rMin, $rMax]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                            <button wire:click="setProfitabilityFilter(<?php echo e($profitabilityTariffIndex); ?>, <?php echo e($rMin); ?>, <?php echo e($rMax ?? 'null'); ?>)"
                                class="px-2 py-1 text-[10px] font-medium rounded-full border transition-all <?php echo e($profitabilityMin == $rMin && ($profitabilityMax == $rMax || ($rMax === null && $profitabilityMax == 999)) ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-white text-gray-500 border-gray-200 hover:border-emerald-400 hover:text-emerald-600'); ?>">
                                <?php echo e($rMax !== null ? "%{$rMin}-{$rMax}" : "%{$rMin}+"); ?>

                            </button>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                        
                        <button x-on:click="showCustom = !showCustom"
                            class="px-2 py-1 text-[10px] font-medium rounded-full border transition-all bg-white text-gray-500 border-gray-200 hover:border-emerald-400">
                            ⚙️ Özel
                        </button>
                    </div>

                    
                    <div x-show="showCustom" x-transition class="flex items-center gap-2 mt-2 pt-2 border-t border-gray-100">
                        <span class="text-xs text-gray-500">Min %</span>
                        <input type="number" x-ref="customMin" value="<?php echo e($profitabilityMin ?? 0); ?>" class="w-16 text-xs text-center border border-gray-300 rounded px-1 py-1" min="0" max="100" step="1">
                        <span class="text-xs text-gray-400">—</span>
                        <span class="text-xs text-gray-500">Max %</span>
                        <input type="number" x-ref="customMax" value="<?php echo e($profitabilityMax != 999 ? $profitabilityMax : 100); ?>" class="w-16 text-xs text-center border border-gray-300 rounded px-1 py-1" min="0" max="100" step="1">
                        <button x-on:click="$wire.setProfitabilityFilter(<?php echo e($profitabilityTariffIndex); ?>, $refs.customMin.value, $refs.customMax.value)"
                            class="px-3 py-1 text-xs bg-emerald-600 text-white rounded hover:bg-emerald-700 font-medium">Filtrele</button>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div class="flex gap-2 mb-4 border-b border-gray-200">
                <button wire:click="$set('tabView', 'products')" 
                    class="px-4 py-2 text-sm font-medium transition-colors border-b-2 <?php echo e($tabView === 'products' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'); ?>">
                    📦 Ürün Bazlı
                </button>
                <button wire:click="$set('tabView', 'categories')" 
                    class="px-4 py-2 text-sm font-medium transition-colors border-b-2 <?php echo e($tabView === 'categories' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'); ?>">
                    📊 Kategori Analizi
                </button>
            </div>

            
            
            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tabView === 'products'): ?>
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">

                
                <div class="hidden xl:block overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-gray-800 text-white">
                                <th class="px-2 py-3 text-center align-middle" rowspan="2" style="min-width:60px">
                                    <span class="text-[10px]">Tarife<br>Seç</span>
                                </th>
                                <th class="px-3 py-3 text-left align-middle" rowspan="2" style="min-width:180px; max-width:250px">
                                    Ürün Bilgileri
                                </th>
                                <th class="px-2 py-3 text-center align-middle" rowspan="2" style="min-width:80px">
                                    <div class="text-[10px] leading-tight">Kargo M</div>
                                    <div class="text-[10px] leading-tight text-gray-400">Üretim M</div>
                                </th>
                                <th class="px-2 py-1.5 text-center bg-orange-600 text-white font-bold border-l border-orange-700 border-r border-orange-700" colspan="1">
                                    <span class="text-[10px]">1. Tarife</span>
                                </th>
                                <th class="px-2 py-1.5 text-center bg-gray-700 font-medium border-r border-gray-600" colspan="1">
                                    <span class="text-[10px]">2. Tarife</span>
                                </th>
                                <th class="px-2 py-1.5 text-center bg-gray-700 font-medium border-r border-gray-600" colspan="1">
                                    <span class="text-[10px]">3. Tarife</span>
                                </th>
                                <th class="px-2 py-1.5 text-center bg-gray-700 font-medium border-r border-gray-600" colspan="1">
                                    <span class="text-[10px]">4. Tarife</span>
                                </th>
                                <th class="px-2 py-3 text-center align-middle bg-emerald-700" rowspan="2" style="min-width:120px">
                                    <span class="text-[10px]">Fiyat Güncelle</span>
                                </th>
                            </tr>
                            <tr class="bg-gray-700 text-gray-300">
                                <th class="px-1 py-1 text-center text-[9px] bg-orange-500/20 border-l border-r border-orange-700">Fiyat · Kom · Kâr</th>
                                <th class="px-1 py-1 text-center text-[9px] border-r border-gray-600">Fiyat · Kom · Kâr</th>
                                <th class="px-1 py-1 text-center text-[9px] border-r border-gray-600">Fiyat · Kom · Kâr</th>
                                <th class="px-1 py-1 text-center text-[9px] border-r border-gray-600">Fiyat · Kom · Kâr</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $report->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <?php
                                    $scenarios = $item->scenario_details;
                                    $sc1 = $scenarios[0] ?? null;
                                    $sc2 = $scenarios[1] ?? null;
                                    $sc3 = $scenarios[2] ?? null;
                                    $sc4 = $scenarios[3] ?? null;
                                    $totalCost = $item->production_cost + $item->shipping_cost;
                                    $selectedIdx = $item->selected_tariff_index;

                                    // Karlılık filtresi: seçilen tarifenin karlılığına göre filtrele
                                    if ($profitabilityTariffIndex !== null && $profitabilityMin !== null && $totalCost > 0) {
                                        $targetSc = $scenarios[$profitabilityTariffIndex] ?? null;
                                        if ($targetSc) {
                                            $profitPct = ($targetSc['net_profit'] / $totalCost) * 100;
                                            if ($profitPct < $profitabilityMin || $profitPct > $profitabilityMax) continue;
                                        } else {
                                            continue; // Bu ürünün hedef tarifesi yok
                                        }
                                    }
                                ?>
                                <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processElementKey('desktop-item-{{ $item->id }}', get_defined_vars()); ?>wire:key="desktop-item-<?php echo e($item->id); ?>" class="hover:bg-gray-50 group transition <?php echo e($item->action === 'warning' ? 'bg-red-50/50' : ''); ?>">
                                    
                                    <td class="px-1 py-2 text-center align-middle">
                                        <div class="flex flex-col gap-0.5 items-center">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($ti = 0; $ti < 4; $ti++): ?>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($scenarios[$ti])): ?>
                                                    <button 
                                                        wire:click="selectTariff(<?php echo e($item->id); ?>, <?php echo e($ti); ?>)"
                                                        class="w-6 h-5 rounded text-[9px] font-bold transition-all <?php echo e($selectedIdx === $ti ? 'bg-orange-500 text-white ring-2 ring-orange-300 shadow-sm' : 'bg-gray-100 text-gray-500 hover:bg-orange-100 hover:text-orange-600'); ?>"
                                                        title="<?php echo e(($ti + 1)); ?>. Tarife Seç">
                                                        <?php echo e($ti + 1); ?>

                                                    </button>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    </td>

                                    
                                    <td class="px-3 py-3 align-top" style="max-width:250px">
                                        <div class="flex items-start gap-2">
                                            <div class="min-w-0 flex-1">
                                                <p class="font-medium text-gray-900 text-xs leading-tight break-words" title="<?php echo e($item->product_name); ?>">
                                                    <?php echo e($item->product_name ?: $item->stock_code); ?>

                                                </p>
                                                <p class="text-[10px] text-indigo-500 font-mono mt-0.5"><?php echo e($item->stock_code); ?></p>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->action === 'update'): ?>
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-green-100 text-green-700 mt-1">🚀 FIRSAT</span>
                                                <?php elseif($item->action === 'warning'): ?>
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-red-100 text-red-600 mt-1">⚠ ZARAR</span>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    
                                    <td class="px-2 py-2 text-center align-middle border-r border-gray-100">
                                        <div class="text-xs font-bold text-gray-800"><?php echo e(number_format($item->shipping_cost, 0)); ?>₺</div>
                                        <div class="text-[10px] text-gray-400"><?php echo e(number_format($item->production_cost, 0)); ?>₺</div>
                                    </td>

                                    
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [$sc1, $sc2, $sc3, $sc4]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $sc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <?php
                                            $isSelected = ($selectedIdx === $idx);
                                            $isBest = $sc && ($sc['is_best'] ?? false);
                                            $hasTariffSelected = ($selectedIdx !== null);
                                            // Seçili tarife: turuncu çerçeve. Başka tarife seçiliyse geri kalanlar gri.
                                            $cellClass = '';
                                            if ($isSelected) {
                                                $cellClass = 'border-2 border-orange-400 bg-orange-50/30';
                                            } elseif ($hasTariffSelected) {
                                                // Başka tarife seçili — bu hücre gri
                                                $cellClass = 'border-gray-100 bg-gray-50/50';
                                            } elseif ($isBest) {
                                                $cellClass = ($idx === 0) ? 'bg-orange-50 border-orange-200' : 'bg-green-50 border-green-200';
                                            } else {
                                                $cellClass = 'border-gray-100';
                                            }
                                        ?>
                                        <td class="px-2 py-2 text-center align-middle border-r <?php echo e($cellClass); ?> <?php echo e($idx === 0 ? 'border-l' : ''); ?> transition-all cursor-pointer"
                                            wire:click="selectTariff(<?php echo e($item->id); ?>, <?php echo e($idx); ?>)"
                                            title="<?php echo e(($idx + 1)); ?>. Tarife seçmek için tıklayın">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sc): ?>
                                                <div class="space-y-0.5">
                                                    <div class="font-bold text-gray-900"><?php echo e(number_format($sc['price'], 0)); ?>₺</div>
                                                    <div class="<?php echo e($idx === 0 ? 'text-orange-600 font-semibold' : 'text-gray-500 font-medium'); ?>">%<?php echo e(number_format($sc['commission'], 1)); ?></div>
                                                    <div class="font-bold text-xs <?php echo e($sc['net_profit'] >= 0 ? 'text-green-600' : 'text-red-600'); ?> cursor-help" title="Karlılık: %<?php echo e($item->production_cost > 0 ? number_format(($sc['net_profit'] / $item->production_cost) * 100, 1) : '0'); ?>">
                                                        <?php echo e(number_format($sc['net_profit'], 0)); ?>₺
                                                    </div>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isSelected): ?>
                                                        <div class="text-[7px] bg-orange-500 text-white px-1 py-0.5 rounded font-bold inline-block">SEÇİLDİ</div>
                                                    <?php elseif($isBest && !$hasTariffSelected): ?>
                                                        <div class="text-[7px] <?php echo e($idx === 0 ? 'bg-orange-500' : 'bg-green-600'); ?> text-white px-1 py-0.5 rounded font-bold inline-block">EN İYİ</div>
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-300">—</span>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                                    
                                    <td class="px-2 py-2 text-center align-middle">
                                        <div class="flex flex-col items-center gap-1 relative" x-data>
                                            <?php
                                                $displayPrice = $item->custom_price ?: ($item->suggested_price ?: $item->current_price);
                                                $suggestion = $suggestedPrices[$item->id] ?? null;
                                            ?>
                                            
                                            <div class="relative flex items-center gap-1">
                                                <div class="relative">
                                                    <input type="number" 
                                                        x-ref="price<?php echo e($item->id); ?>"
                                                        value="<?php echo e(number_format($displayPrice, 2, '.', '')); ?>"
                                                        class="w-20 text-xs text-center border border-gray-300 rounded-md px-1 py-1 font-bold focus:ring-2 focus:ring-orange-400 focus:border-orange-400 <?php echo e($item->custom_price ? 'bg-orange-50 border-orange-300' : ''); ?>"
                                                        step="0.01"
                                                        min="0"
                                                        title="Özel fiyat gir">
                                                </div>
                                                
                                                
                                                <button wire:click="getAiPriceSuggestion(<?php echo e($item->id); ?>)" 
                                                    class="p-1.5 rounded-md bg-indigo-50 text-indigo-600 hover:bg-indigo-100 hover:text-indigo-700 transition-colors"
                                                    title="AI ile Fiyat Öner">
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($suggestion && ($suggestion['loading'] ?? false)): ?>
                                                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                    <?php else: ?>
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </button>
                                            </div>

                                            
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($suggestion && !($suggestion['loading'] ?? false) && !isset($suggestion['error'])): ?>
                                                <div x-data="{ showPop: true }" x-show="showPop" x-transition
                                                     class="absolute top-full left-1/2 -translate-x-1/2 mt-1 z-50 w-32 bg-white rounded-md shadow-lg border border-indigo-100 p-1.5 text-center">
                                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                                                        <span class="text-[8px] text-gray-400">AI Önerisi</span>
                                                        <span x-on:click.stop="showPop = false" 
                                                            style="cursor:pointer; background:#ef4444; color:white; font-size:9px; font-weight:bold; padding:0 4px; border-radius:3px; display:inline-block; line-height:1.5;">✕</span>
                                                    </div>
                                                    <div class="font-bold text-indigo-600 text-xs"><?php echo e(number_format($suggestion['price'], 2)); ?> ₺</div>
                                                    <p class="text-[7px] text-gray-400 italic my-0.5 leading-tight"><?php echo e(Str::limit($suggestion['reason'], 30)); ?></p>
                                                    <button wire:click="applySuggestedPrice(<?php echo e($item->id); ?>)" 
                                                        class="w-full bg-indigo-600 text-white text-[8px] py-0.5 rounded hover:bg-indigo-700 transition">
                                                        Uygula
                                                    </button>
                                                </div>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                            <button 
                                                x-on:click="$wire.updateCustomPrice(<?php echo e($item->id); ?>, $refs.price<?php echo e($item->id); ?>.value)"
                                                class="bg-orange-500 hover:bg-orange-600 text-white text-[9px] font-bold px-3 py-1 rounded transition-colors shadow-sm w-full">
                                                Kaydet
                                            </button>
                                            
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->custom_price): ?>
                                                <div class="flex items-center gap-1">
                                                    <span class="text-[8px] text-emerald-600 font-medium">✅ Düzenlendi</span>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->custom_price != ($suggestion['price'] ?? 0)): ?>
                                                        
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </div>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    </td>

                                </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">Sonuç bulunamadı</td>
                                </tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>

                
                <div class="hidden lg:block xl:hidden overflow-x-auto">
                    <table class="w-full text-xs" style="min-width:800px">
                        <thead class="bg-gray-800 text-white">
                            <tr>
                                <th class="px-1 py-2 text-center text-[10px]" style="width:50px">Tarife<br>Seç</th>
                                <th class="px-3 py-2 text-left text-[10px]" style="min-width:130px">Ürün</th>
                                <th class="px-2 py-2 text-center text-[10px]"><div>Kargo</div><div class="text-gray-400">Üretim</div></th>
                                <th class="px-2 py-2 text-center text-[10px] bg-orange-600 border-l border-r border-orange-700">1. Tarife</th>
                                <th class="px-2 py-2 text-center text-[10px]">2. Tarife</th>
                                <th class="px-2 py-2 text-center text-[10px]">3. Tarife</th>
                                <th class="px-2 py-2 text-center text-[10px]">4. Tarife</th>
                                <th class="px-2 py-2 text-center text-[10px] bg-emerald-700">Fiyat<br>Güncelle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $report->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <?php
                                    $scenarios = $item->scenario_details;
                                    $sc1 = $scenarios[0] ?? null; $sc2 = $scenarios[1] ?? null;
                                    $sc3 = $scenarios[2] ?? null; $sc4 = $scenarios[3] ?? null;
                                    $totalCost = $item->production_cost + $item->shipping_cost;
                                    $selectedIdx = $item->selected_tariff_index;

                                    // Karlılık filtresi
                                    if ($profitabilityTariffIndex !== null && $profitabilityMin !== null && $item->production_cost > 0) {
                                        $targetSc = $scenarios[$profitabilityTariffIndex] ?? null;
                                        if ($targetSc) {
                                            $profitPct = ($targetSc['net_profit'] / $item->production_cost) * 100;
                                            if ($profitPct < $profitabilityMin || $profitPct > $profitabilityMax) continue;
                                        } else {
                                            continue;
                                        }
                                    }
                                ?>
                                <tr class="hover:bg-gray-50 <?php echo e($item->action === 'warning' ? 'bg-red-50/50' : ''); ?>">
                                    <td class="px-1 py-2 text-center">
                                        <div class="flex flex-col gap-0.5 items-center">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($ti = 0; $ti < 4; $ti++): ?>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($scenarios[$ti])): ?>
                                                    <button wire:click="selectTariff(<?php echo e($item->id); ?>, <?php echo e($ti); ?>)"
                                                        class="w-5 h-4 rounded text-[8px] font-bold <?php echo e($selectedIdx === $ti ? 'bg-orange-500 text-white ring-1 ring-orange-300' : 'bg-gray-100 text-gray-500 hover:bg-orange-100'); ?>">
                                                        <?php echo e($ti + 1); ?>

                                                    </button>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <p class="font-medium text-gray-900" title="<?php echo e($item->product_name); ?>"><?php echo e($item->product_name ?: $item->stock_code); ?></p>
                                        <p class="text-[9px] text-indigo-500 font-mono"><?php echo e($item->stock_code); ?></p>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->action === 'update'): ?><span class="text-[8px] bg-green-100 text-green-700 px-1 rounded font-bold">FIRSAT</span><?php elseif($item->action === 'warning'): ?><span class="text-[8px] bg-red-100 text-red-600 px-1 rounded font-bold">ZARAR</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                    <td class="px-1 py-2 text-center">
                                        <div class="text-[10px] font-bold text-gray-800"><?php echo e(number_format($item->shipping_cost, 0)); ?>₺</div>
                                        <div class="text-[9px] text-gray-400"><?php echo e(number_format($item->production_cost, 0)); ?>₺</div>
                                    </td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [$sc1, $sc2, $sc3, $sc4]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $sc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <?php
                                            $isSelected = ($selectedIdx === $idx);
                                            $isBest = $sc && ($sc['is_best'] ?? false);
                                            $cellCls = $isSelected ? 'border-2 border-orange-400 bg-orange-50/30' : ($isBest ? ($idx === 0 ? 'bg-orange-50' : 'bg-green-50') : '');
                                        ?>
                                        <td class="px-1 py-2 text-center cursor-pointer <?php echo e($cellCls); ?> <?php echo e($idx === 0 ? 'border-l border-r border-orange-100' : 'border-r border-gray-50'); ?>"
                                            wire:click="selectTariff(<?php echo e($item->id); ?>, <?php echo e($idx); ?>)">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sc): ?>
                                                <div class="text-[10px] font-bold"><?php echo e(number_format($sc['price'], 0)); ?>₺</div>
                                                <div class="text-[9px] <?php echo e($idx === 0 ? 'text-orange-600' : 'text-gray-400'); ?>">%<?php echo e(number_format($sc['commission'], 1)); ?></div>
                                                <div class="text-[10px] font-bold <?php echo e($sc['net_profit'] >= 0 ? 'text-green-600' : 'text-red-600'); ?> cursor-help" title="Karlılık: %<?php echo e($item->production_cost > 0 ? number_format(($sc['net_profit'] / $item->production_cost) * 100, 1) : '0'); ?>"><?php echo e(number_format($sc['net_profit'], 0)); ?>₺</div>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isSelected): ?><div class="text-[7px] bg-orange-500 text-white px-0.5 rounded font-bold inline-block mt-0.5">SEÇİLDİ</div>
                                                <?php elseif($isBest): ?><div class="text-[7px] bg-orange-500 text-white px-0.5 rounded font-bold inline-block mt-0.5">EN İYİ</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <?php else: ?> <span class="text-gray-300">—</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    <td class="px-1 py-2 text-center align-middle">
                                        <div class="flex flex-col items-center gap-1 relative" x-data="{ showSuggestion: true }">
                                            <?php
                                                $dp = $item->custom_price ?: ($item->suggested_price ?: $item->current_price);
                                                $suggestion = $suggestedPrices[$item->id] ?? null;
                                            ?>
                                            
                                            <div class="relative flex items-center gap-1 justify-center">
                                                <input type="number" x-ref="tp<?php echo e($item->id); ?>" value="<?php echo e(number_format($dp, 2, '.', '')); ?>" class="w-16 text-[10px] text-center border rounded px-1 py-0.5 font-bold <?php echo e($item->custom_price ? 'bg-orange-50 border-orange-300' : 'border-gray-300'); ?>" step="0.01" min="0">
                                                
                                                <button wire:click="getAiPriceSuggestion(<?php echo e($item->id); ?>)" class="p-1 rounded bg-indigo-50 text-indigo-600 hover:bg-indigo-100 transition-colors" title="AI Fiyat Öner">
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($suggestion && ($suggestion['loading'] ?? false)): ?>
                                                        <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                    <?php else: ?>
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </button>
                                            </div>

                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($suggestion && !($suggestion['loading'] ?? false) && !isset($suggestion['error'])): ?>
                                                <div x-show="showSuggestion" x-transition
                                                     class="absolute top-full left-1/2 -translate-x-1/2 mt-1 z-50 w-44 bg-white rounded-lg shadow-xl border border-indigo-200 p-2">
                                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                                        <span class="text-[10px] text-gray-500">AI Önerisi</span>
                                                        <span x-on:click.stop="showSuggestion = false" 
                                                            style="cursor:pointer; background:#ef4444; color:white; font-size:11px; font-weight:bold; padding:1px 6px; border-radius:4px; display:inline-block; line-height:1.4;">X</span>
                                                    </div>
                                                    <div class="font-bold text-indigo-700 text-base text-center mb-1"><?php echo e(number_format($suggestion['price'], 2)); ?> ₺</div>
                                                    <p class="text-[8px] text-gray-400 italic mb-2 leading-tight text-center"><?php echo e(Str::limit($suggestion['reason'], 50)); ?></p>
                                                    <button wire:click="applySuggestedPrice(<?php echo e($item->id); ?>)" class="w-full bg-indigo-600 text-white text-[10px] py-1 rounded hover:bg-indigo-700 transition font-medium">Uygula</button>
                                                </div>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                            <button x-on:click="$wire.updateCustomPrice(<?php echo e($item->id); ?>, $refs.tp<?php echo e($item->id); ?>.value)"
                                                class="bg-orange-500 text-white text-[8px] font-bold px-2 py-0.5 rounded mt-0.5 block mx-auto w-full">Güncelle</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <tr><td colspan="8" class="p-8 text-center text-gray-500">Sonuç bulunamadı</td></tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>

                
                <div class="lg:hidden divide-y divide-gray-200">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $report->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <?php
                            $scenarios = $item->scenario_details;
                            $totalCost = $item->production_cost + $item->shipping_cost;
                            $selectedIdx = $item->selected_tariff_index;

                            // Karlılık filtresi
                            if ($profitabilityTariffIndex !== null && $profitabilityMin !== null && $item->production_cost > 0) {
                                $targetSc = $scenarios[$profitabilityTariffIndex] ?? null;
                                if ($targetSc) {
                                    $profitPct = ($targetSc['net_profit'] / $item->production_cost) * 100;
                                    if ($profitPct < $profitabilityMin || $profitPct > $profitabilityMax) continue;
                                } else {
                                    continue;
                                }
                            }
                        ?>
                        <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processElementKey('mobile-item-{{ $item->id }}', get_defined_vars()); ?>wire:key="mobile-item-<?php echo e($item->id); ?>" class="p-4 <?php echo e($item->action === 'update' ? 'bg-green-50/50' : ($item->action === 'warning' ? 'bg-red-50/50' : '')); ?>">
                            
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-start gap-2 min-w-0 flex-1">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 line-clamp-2"><?php echo e($item->product_name ?: $item->stock_code); ?></p>
                                        <p class="text-[10px] text-indigo-500 font-mono mt-0.5"><?php echo e($item->stock_code); ?></p>
                                    </div>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->action === 'update'): ?>
                                    <span class="text-[9px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-bold flex-shrink-0 ml-2">🚀 FIRSAT</span>
                                <?php elseif($item->action === 'warning'): ?>
                                    <span class="text-[9px] bg-red-100 text-red-600 px-1.5 py-0.5 rounded font-bold flex-shrink-0 ml-2">⚠ ZARAR</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            
                            <div class="flex justify-center gap-4 text-[10px] text-gray-400 mb-2">
                                <span>Kargo: <span class="font-medium text-gray-700"><?php echo e(number_format($item->shipping_cost, 0)); ?>₺</span></span>
                                <span>Üretim: <span class="font-medium text-gray-700"><?php echo e(number_format($item->production_cost, 0)); ?>₺</span></span>
                            </div>

                            
                            <div class="grid grid-cols-4 gap-1.5">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($scenarios): ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $scenarios; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $sc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <?php
                                            $isSelected = ($selectedIdx === $idx);
                                            $isBest = ($sc['is_best'] ?? false);
                                            $hasTariffSelected = ($selectedIdx !== null);
                                        ?>
                                        <div wire:click="selectTariff(<?php echo e($item->id); ?>, <?php echo e($idx); ?>)"
                                             class="rounded-lg p-2 text-center cursor-pointer transition-all <?php echo e($isSelected ? 'bg-orange-50 border-2 border-orange-400 ring-2 ring-orange-200 shadow-md' : ($hasTariffSelected ? 'bg-gray-50 border border-gray-200' : ($isBest ? ($idx === 0 ? 'bg-orange-50 border-2 border-orange-300 ring-1 ring-orange-200' : 'bg-green-50 border-2 border-green-300 ring-1 ring-green-200') : 'bg-gray-50 border border-gray-200 hover:border-orange-200'))); ?>">
                                            <div class="text-[9px] font-bold <?php echo e($idx === 0 ? 'text-orange-600' : 'text-gray-500'); ?> mb-1">
                                                <?php echo e($idx === 0 ? 'Mevcut' : ($idx+1) . '. Tarife'); ?>

                                            </div>
                                            <div class="text-xs font-bold text-gray-900"><?php echo e(number_format($sc['price'], 0)); ?>₺</div>
                                            <div class="text-[9px] <?php echo e($idx === 0 ? 'text-orange-500' : 'text-gray-400'); ?>">%<?php echo e(number_format($sc['commission'], 1)); ?></div>
                                            <div class="text-xs font-bold mt-0.5 <?php echo e($sc['net_profit'] >= 0 ? 'text-green-600' : 'text-red-500'); ?>" title="Karlılık: %<?php echo e($item->production_cost > 0 ? number_format(($sc['net_profit'] / $item->production_cost) * 100, 1) : '0'); ?>">
                                                <?php echo e(number_format($sc['net_profit'], 0)); ?>₺
                                            </div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isSelected): ?>
                                                <div class="text-[7px] bg-orange-500 text-white px-1 py-0.5 rounded font-bold mt-0.5 inline-block">SEÇİLDİ</div>
                                            <?php elseif($isBest && !$hasTariffSelected): ?>
                                                <div class="text-[7px] bg-orange-500 text-white px-1 py-0.5 rounded font-bold mt-0.5 inline-block">EN İYİ</div>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <?php else: ?>
                                    <div class="col-span-4 text-center text-gray-400 text-xs py-2">Senaryo verisi yok</div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            
                            <div x-data="{ showSuggestion: true }" class="flex flex-col items-center gap-2 mt-3 pt-2 border-t border-gray-100 relative">
                                <?php 
                                    $dp = $item->custom_price ?: ($item->suggested_price ?: $item->current_price);
                                    $suggestion = $suggestedPrices[$item->id] ?? null;
                                ?>
                                
                                <div class="flex items-center gap-2">
                                    <input type="number" x-ref="mp<?php echo e($item->id); ?>" value="<?php echo e(number_format($dp, 2, '.', '')); ?>" 
                                        class="w-28 text-xs text-center border rounded-md px-2 py-1.5 font-bold <?php echo e($item->custom_price ? 'bg-orange-50 border-orange-300' : 'border-gray-300'); ?>" step="0.01" min="0">
                                    
                                    <button wire:click="getAiPriceSuggestion(<?php echo e($item->id); ?>)" class="p-2 rounded-md bg-indigo-50 text-indigo-600 hover:bg-indigo-100 transition-colors shadow-sm" title="AI Fiyat Öner">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($suggestion && ($suggestion['loading'] ?? false)): ?>
                                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        <?php else: ?>
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </button>
                                </div>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($suggestion && !($suggestion['loading'] ?? false) && !isset($suggestion['error'])): ?>
                                    <div x-show="showSuggestion" x-transition
                                         class="w-full bg-indigo-50 rounded px-2 py-1.5 border border-indigo-200 flex items-center justify-between gap-2">
                                        <span class="text-[9px] text-indigo-500 font-semibold whitespace-nowrap">AI:</span>
                                        <span class="font-bold text-indigo-700 text-xs whitespace-nowrap"><?php echo e(number_format($suggestion['price'], 2)); ?> ₺</span>
                                        <button type="button" wire:click="applySuggestedPrice(<?php echo e($item->id); ?>)" class="bg-indigo-600 text-white text-[8px] px-2 py-0.5 rounded hover:bg-indigo-700 transition font-medium whitespace-nowrap">Uygula</button>
                                        <span x-on:click.stop="showSuggestion = false" 
                                            style="cursor:pointer; background:#ef4444; color:white; font-size:9px; font-weight:bold; padding:0 4px; border-radius:3px; display:inline-block; line-height:1.5;">✕</span>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <button x-on:click="$wire.updateCustomPrice(<?php echo e($item->id); ?>, $refs.mp<?php echo e($item->id); ?>.value)"
                                    class="bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold px-4 py-1.5 rounded-md transition-colors shadow-sm w-full">
                                    Fiyat Güncelle
                                </button>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->custom_price): ?>
                                    <span class="text-[9px] text-emerald-600 font-medium">✅</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <div class="p-8 text-center text-gray-500">Sonuç bulunamadı</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tabView === 'categories'): ?>
            <div class="overflow-x-auto p-4 bg-white rounded-lg border border-gray-200">
                <div class="mb-4 bg-indigo-50 border border-indigo-100 rounded-lg p-4 flex items-center gap-4">
                    <div class="p-2 bg-indigo-100 rounded-lg text-indigo-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <div>
                        <h4 class="font-bold text-indigo-900">Kategori Performans Özeti</h4>
                        <p class="text-xs text-indigo-700">
                            <?php echo e(count($this->categoryReport)); ?> kategori analiz edildi.
                            Ürün isimlerine göre otomatik kategorize edilmiştir. En karlı kategoriler en üstte sıralanır.
                        </p>
                    </div>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($this->categoryReport) === 0): ?>
                    <div class="p-8 text-center text-gray-500 border-2 border-dashed border-gray-200 rounded-lg">
                        <p>Kategori verisi bulunamadı.</p>
                        <p class="text-xs mt-1">Ürün isimleri ayrıştırılamadı veya rapor boş.</p>
                    </div>
                <?php else: ?>


                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-700 font-bold uppercase text-xs">
                        <tr>
                            <th class="px-4 py-3 rounded-l-lg">Kategori</th>
                            <th class="px-4 py-3 text-center">Ürün Adedi</th>
                            <th class="px-4 py-3 text-right">Toplam Maliyet</th>
                            <th class="px-4 py-3 text-right">Potansiyel Ciro</th>
                            <th class="px-4 py-3 text-right">Net Kâr</th>
                            <th class="px-4 py-3 text-center rounded-r-lg">Marj %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->categoryReport; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3 font-medium text-gray-900"><?php echo e($cat['name']); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs font-bold"><?php echo e($cat['count']); ?></span>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-500"><?php echo e(number_format($cat['cost'], 2)); ?> ₺</td>
                                <td class="px-4 py-3 text-right font-medium"><?php echo e(number_format($cat['revenue'], 2)); ?> ₺</td>
                                <td class="px-4 py-3 text-right font-bold <?php echo e($cat['profit'] > 0 ? 'text-emerald-600' : 'text-red-600'); ?>">
                                    <?php echo e(number_format($cat['profit'], 2)); ?> ₺
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php 
                                        $margin = $cat['revenue'] > 0 ? ($cat['profit'] / $cat['revenue']) * 100 : 0;
                                        $color = $margin >= 30 ? 'bg-emerald-100 text-emerald-700' : ($margin >= 15 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700');
                                    ?>
                                    <span class="<?php echo e($color); ?> px-2 py-1 rounded text-xs font-bold">
                                        %<?php echo e(number_format($margin, 1)); ?>

                                    </span>
                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </tbody>
                </table>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>



    
    
    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeTab === 'history'): ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->reports->isEmpty()): ?>
            <div class="text-center py-12">
                <svg class="w-16 h-16 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900">Henüz rapor yok</h3>
                <p class="mt-2 text-sm text-gray-500">İlk analizi çalıştırarak buraya rapor ekleyin.</p>
                <button wire:click="switchTab('analyze')" class="mt-4 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 min-h-[44px]">
                    Yeni Analiz Başlat
                </button>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 lg:gap-6">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->reports; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rpt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition p-4 lg:p-5">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1 min-w-0">
                                <h4 class="font-medium text-gray-900 truncate"><?php echo e($rpt->name); ?></h4>
                                <p class="text-xs text-gray-400 mt-0.5"><?php echo e($rpt->created_at->format('d.m.Y H:i')); ?></p>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium <?php echo e($rpt->status === 'applied' ? 'bg-blue-100 text-blue-800' : 
                                ($rpt->status === 'exported' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800')); ?>">
                                <?php echo e($rpt->status === 'applied' ? 'Uygulandı' : ($rpt->status === 'exported' ? 'İndirildi' : 'Tamamlandı')); ?>

                            </span>
                        </div>
                        
                        <div class="grid grid-cols-3 gap-2 text-center bg-gray-50 rounded-lg p-3 mb-3">
                            <div>
                                <div class="text-xs text-gray-400">Ürün</div>
                                <div class="text-lg font-bold text-gray-900"><?php echo e($rpt->total_products); ?></div>
                            </div>
                            <div>
                                <div class="text-xs text-emerald-500">Fırsat</div>
                                <div class="text-lg font-bold text-emerald-600"><?php echo e($rpt->opportunity_count); ?></div>
                            </div>
                            <div>
                                <div class="text-xs text-green-500">Ek Kâr</div>
                                <div class="text-sm font-bold text-green-600">+<?php echo e(number_format($rpt->total_extra_profit, 0)); ?> ₺</div>
                            </div>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rpt->original_filename): ?>
                            <p class="text-[10px] text-gray-400 truncate mb-3">📄 <?php echo e($rpt->original_filename); ?></p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div class="flex gap-2">
                            <button wire:click="viewReport(<?php echo e($rpt->id); ?>)"
                                class="flex-1 px-3 py-2 text-sm bg-gray-900 text-white rounded-lg hover:bg-gray-800 min-h-[44px]">
                                Görüntüle
                            </button>
                            <button wire:click="deleteReport(<?php echo e($rpt->id); ?>)"
                                wire:confirm="Bu raporu silmek istediğinize emin misiniz?"
                                class="px-3 py-2 text-sm text-red-600 border border-red-200 rounded-lg hover:bg-red-50 min-h-[44px]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    
    
    
    
    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeReportId): ?>
        <button wire:click="toggleChat"
            class="fixed bottom-5 right-5 w-11 h-11 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full flex items-center justify-center transition-all duration-200 hover:scale-105 active:scale-95 shadow-lg shadow-indigo-600/25"
            style="z-index: 9998;">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$showChat): ?>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                </svg>
            <?php else: ?>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </button>

        
        <div x-data="{ show: <?php if ((object) ('showChat') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showChat'->value()); ?>')<?php echo e('showChat'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showChat'); ?>')<?php endif; ?> }"
             x-show="show"
             x-cloak
             @keydown.escape.window="show = false"
             class="fixed inset-y-0 right-0 w-full sm:w-96 bg-white shadow-2xl flex flex-col"
             style="z-index: 9999;"
             x-transition:enter="transition transform ease-out duration-250"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition transform ease-in duration-200"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full">
            
            
            <div class="px-4 py-2.5 bg-indigo-600 text-white flex justify-between items-center shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-white/15 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-sm">ZOLM AI</h3>
                        <p class="text-[10px] text-indigo-200">Rapor Asistanı</p>
                    </div>
                </div>
                <button @click="show = false" class="w-7 h-7 rounded-md flex items-center justify-center hover:bg-white/10 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->activeReport): ?>
                <div x-data="{ toolsOpen: false }" class="px-3 py-2 bg-gray-50 border-b border-gray-100">
                    <button type="button" @click="toolsOpen = !toolsOpen" class="w-full flex items-center justify-between text-xs">
                        <span class="flex items-center gap-1.5 font-semibold text-gray-600">
                            <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.42 15.17l-5.658 3.286 1.082-6.302-4.58-4.466 6.325-.919L11.42 1l2.83 5.769 6.326.919-4.58 4.466 1.081 6.302z"/></svg>
                            AI Araçları
                        </span>
                        <svg class="w-3.5 h-3.5 text-gray-400 transition-transform" :class="toolsOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div x-show="toolsOpen" x-transition x-cloak class="mt-2 space-y-1.5">
                        <?php $report = $this->activeReport; ?>

                        
                        <div x-data="{ aiExpanded: false }" class="bg-white rounded-lg border border-gray-200 p-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-5 h-5 rounded bg-indigo-100 flex items-center justify-center shrink-0">
                                        <svg class="w-3 h-3 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    </div>
                                    <span class="text-xs font-medium text-gray-700">AI Danışman</span>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->ai_analysis): ?>
                                    <button type="button" @click="aiExpanded = !aiExpanded" class="px-2 py-0.5 text-[10px] font-medium rounded text-indigo-600 hover:bg-indigo-50 transition">
                                        <span x-text="aiExpanded ? 'Gizle' : 'Göster'">Göster</span>
                                    </button>
                                <?php elseif(!$isProcessing): ?>
                                    <button wire:click="generateAIAnalysis" class="px-2 py-0.5 bg-indigo-600 text-white text-[10px] font-medium rounded hover:bg-indigo-700 transition">Analiz</button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isProcessing && str_contains($message, 'Yapay zeka')): ?>
                                <div class="mt-1.5 animate-pulse"><div class="h-1 bg-indigo-100 rounded w-3/4"></div></div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->ai_analysis): ?>
                                <div x-show="aiExpanded" x-transition x-cloak class="mt-2 prose prose-indigo prose-xs max-w-none bg-gray-50 rounded p-2 text-[11px] leading-relaxed max-h-48 overflow-y-auto border border-gray-100">
                                    <?php echo Str::markdown($report->ai_analysis); ?>

                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->items->where('action', 'warning')->count() > 0): ?>
                            <div x-data="{ lossExpanded: false }" class="bg-white rounded-lg border border-gray-200 p-2">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="w-5 h-5 rounded bg-red-50 flex items-center justify-center shrink-0">
                                            <svg class="w-3 h-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                        </div>
                                        <span class="text-xs font-medium text-gray-700">Zarar Denetimi</span>
                                        <span class="text-[9px] text-red-500 font-medium"><?php echo e($report->items->where('action', 'warning')->count()); ?></span>
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->loss_analysis): ?>
                                        <button type="button" @click="lossExpanded = !lossExpanded" class="px-2 py-0.5 text-[10px] font-medium rounded text-red-600 hover:bg-red-50 transition">
                                            <span x-text="lossExpanded ? 'Gizle' : 'Göster'">Göster</span>
                                        </button>
                                    <?php elseif(!$isProcessing): ?>
                                        <button wire:click="analyzeLosses" class="px-2 py-0.5 bg-red-600 text-white text-[10px] font-medium rounded hover:bg-red-700 transition">Denetle</button>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isProcessing && str_contains($message, 'Zarar')): ?>
                                    <div class="mt-1.5 animate-pulse"><div class="h-1 bg-red-100 rounded w-3/4"></div></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->loss_analysis): ?>
                                    <div x-show="lossExpanded" x-transition x-cloak class="mt-2 prose prose-red prose-xs max-w-none bg-gray-50 rounded p-2 text-[11px] leading-relaxed max-h-48 overflow-y-auto border border-gray-100">
                                        <?php echo Str::markdown($report->loss_analysis); ?>

                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <div class="flex-1 overflow-y-auto p-3 space-y-3 bg-gray-50" id="chat-messages">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->currentConversation): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->currentConversation->messages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $msg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($msg['role'] === 'system'): ?> <?php continue; ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <div class="flex <?php echo e($msg['role'] === 'user' ? 'justify-end' : 'justify-start'); ?>">
                            <div class="max-w-[85%] rounded-xl p-2.5 text-sm <?php echo e($msg['role'] === 'user' 
                                    ? 'bg-indigo-600 text-white rounded-br-sm' 
                                    : 'bg-white text-gray-700 rounded-bl-sm border border-gray-100 shadow-sm'); ?>">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($msg['role'] !== 'user'): ?>
                                    <div class="prose prose-sm max-w-none text-gray-700" style="font-size: 13px;">
                                        <?php echo Str::markdown($msg['content']); ?>

                                    </div>
                                <?php else: ?>
                                    <?php echo e($msg['content']); ?>

                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <div class="text-[9px] mt-1 <?php echo e($msg['role'] === 'user' ? 'text-indigo-200' : 'text-gray-400'); ?> text-right">
                                    <?php echo e(\Carbon\Carbon::parse($msg['timestamp'])->format('H:i')); ?>

                                </div>
                            </div>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isChatting): ?>
                        <div class="flex justify-start">
                            <div class="bg-white text-gray-400 rounded-xl rounded-bl-sm p-2.5 text-xs border border-gray-100 shadow-sm">
                                <div class="flex items-center gap-1">
                                    <div class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-bounce" style="animation-delay: 0ms;"></div>
                                    <div class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-bounce" style="animation-delay: 150ms;"></div>
                                    <div class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-bounce" style="animation-delay: 300ms;"></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php else: ?>
                    <div class="text-center mt-12">
                        <div class="w-10 h-10 mx-auto rounded-xl bg-indigo-50 flex items-center justify-center mb-3">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                        </div>
                        <p class="text-gray-500 text-xs">Raporunuz hakkında soru sorun</p>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div class="p-2.5 bg-white border-t border-gray-100 shrink-0">
                <form wire:submit.prevent="sendMessage" class="flex gap-2">
                    <input type="text" 
                        wire:model="chatMessage" 
                        placeholder="Bir soru sorun..." 
                        class="flex-1 rounded-lg text-sm min-h-[38px] px-3 border border-gray-200 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400 bg-gray-50"
                        <?php echo e($isChatting ? 'disabled' : ''); ?>>
                    <button type="submit" 
                        class="w-9 h-9 bg-indigo-600 text-white rounded-lg flex items-center justify-center hover:bg-indigo-700 transition disabled:opacity-50"
                        <?php echo e($isChatting ? 'disabled' : ''); ?>>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
    <?php
        $__scriptKey = '3272902868-0';
        ob_start();
    ?>
<script>
    Livewire.on('chat-scroll', () => {
        const el = document.getElementById('chat-messages');
        if(el) setTimeout(() => el.scrollTop = el.scrollHeight, 100);
    });
</script>
    <?php
        $__output = ob_get_clean();

        \Livewire\store($this)->push('scripts', $__output, $__scriptKey)
    ?>

<?php /**PATH C:\laragon\www\zolm\resources\views/livewire/tariff-optimizer.blade.php ENDPATH**/ ?>