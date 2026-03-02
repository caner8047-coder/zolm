<div>
    <!-- Page Header -->
    <div class="mb-6 lg:mb-8">
        <h1 class="text-xl lg:text-2xl font-bold text-gray-900">Üretim Motoru</h1>
        <p class="text-gray-500 mt-1 text-sm lg:text-base">Sipariş XLS dosyasını yükleyerek üretim raporlarını oluşturun</p>
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
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->profiles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $profile): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <option value="<?php echo e($profile->id); ?>">
                                    <?php echo e($profile->name); ?>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profile->is_ai_generated): ?> (AI) <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profile->is_default): ?> ★ <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
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
                            <span class="truncate"><?php echo e($file ? $file->getClientOriginalName() : 'sipariş xls ↓'); ?></span>
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

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['file'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="mt-2 text-sm text-red-600"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <!-- Selected Profile Info -->
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->selectedProfile && $this->selectedProfile->is_ai_generated): ?>
            <div class="bg-purple-50 rounded-lg border border-purple-200 p-4">
                <div class="flex items-center space-x-2 mb-2">
                    <svg class="w-5 h-5 text-purple-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span class="font-medium text-purple-900">AI Profili: <?php echo e($this->selectedProfile->name); ?></span>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->selectedProfile->ai_prompt): ?>
                <p class="text-sm text-purple-700"><?php echo e(Str::limit($this->selectedProfile->ai_prompt, 150)); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <!-- Processing Animation -->
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isProcessing): ?>
            <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                <div class="flex justify-center space-x-1 mb-4">
                    <span class="w-3 h-3 bg-gray-900 rounded-full animate-bounce"></span>
                    <span class="w-3 h-3 bg-gray-900 rounded-full animate-bounce" style="animation-delay: 0.1s"></span>
                    <span class="w-3 h-3 bg-gray-900 rounded-full animate-bounce" style="animation-delay: 0.2s"></span>
                </div>
                <p class="text-gray-600">motor çalışıyor... (animasyon)</p>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <!-- Message -->
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($message): ?>
            <div class="p-4 rounded-lg <?php echo e($messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : ($messageType === 'error' ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-blue-50 border border-blue-200 text-blue-700')); ?>">
                <?php echo e($message); ?>

            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <!-- Generated Files -->
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($generatedFiles) > 0): ?>
            <div class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-200">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $generatedFiles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4">
                    <div class="flex items-start sm:items-center space-x-3 min-w-0">
                        <span class="text-gray-400 flex-shrink-0"><?php echo e($index + 1); ?>.</span>
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 truncate"><?php echo e($file['filename']); ?></p>
                            <p class="text-sm text-gray-500">Çıktı xls dosyası (motorun verdiği çıktı)</p>
                        </div>
                    </div>
                    <a 
                        href="<?php echo e(route('download', $file['id'])); ?>"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex-shrink-0"
                    >
                        xls dışarı aktar
                    </a>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <!-- Right Column: Actions -->
        <div class="space-y-4 lg:space-y-6">
            <!-- New AI Profile Button -->
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <a href="<?php echo e(route('profile.wizard')); ?>" class="flex items-center justify-center w-full px-4 py-3 bg-purple-600 text-white font-medium rounded-lg hover:bg-purple-700 transition-colors">
                    <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    AI ile Yeni Profil
                </a>
                <p class="text-xs text-gray-400 mt-2 text-center">Örnek dosya yükle, AI kuralları oluştursun</p>
            </div>

            <!-- History Button -->
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <p class="text-xs text-gray-500 mb-2">geçmiş kaydedilen raporları seçip ekrana getir</p>
                <a href="<?php echo e(route('reports')); ?>" class="block w-full px-4 py-2 text-sm text-center border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    gün bazında rapor seç
                </a>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($generatedFiles) > 0): ?>
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
                <button class="w-full px-4 py-3 bg-gray-900 text-white font-medium rounded-lg hover:bg-gray-800 transition-colors">
                    Tüm motor çıktılarını kaydet
                </button>
                <p class="text-xs text-gray-400 mt-2 text-center">motorun verdiği çıktıyı tarihli bir şekilde kaydet</p>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <!-- AI Chat Section -->
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <h4 class="font-medium text-gray-900 mb-2">ai chat</h4>
                <p class="text-xs text-gray-500 mb-4">üretim listesi çıktılarını otomatik üretim müdür gibi yorumla önerilerde bulun</p>
                <a href="<?php echo e(route('ai-chat')); ?>" class="block w-full px-4 py-2 text-sm text-center bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                    AI Chat'e Git →
                </a>
            </div>
        </div>
    </div>
</div>
<?php /**PATH C:\laragon\www\zolm\resources\views/livewire/production-motor.blade.php ENDPATH**/ ?>