
<div x-data="{ open: <?php if ((object) ('showImportModal') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showImportModal'->value()); ?>')<?php echo e('showImportModal'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showImportModal'); ?>')<?php endif; ?> }" x-show="open" class="relative z-50" style="display: none;">
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div x-show="open"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
                 class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl transition-all w-full sm:my-8 sm:max-w-lg">

                
                <div class="bg-white px-4 pt-5 pb-4 sm:px-6 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900">📥 Excel Dosyası İçe Aktar</h3>
                    <p class="text-sm text-gray-500 mt-1">Trendyol dışa aktarma veya manuel ürün listesini yükleyin. Dosya formatı otomatik algılanır.</p>
                </div>

                
                <div class="px-4 py-5 sm:px-6">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$importResult): ?>
                        
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-indigo-400 transition-colors">
                            <input type="file" wire:model="importFile" accept=".xlsx,.xls" class="hidden" id="importFileInput">
                            <label for="importFileInput" class="cursor-pointer">
                                <svg class="mx-auto h-10 w-10 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <p class="text-sm text-gray-600">Dosya seçmek için tıklayın</p>
                                <p class="text-xs text-gray-400 mt-1">.xlsx veya .xls (max 10MB)</p>
                            </label>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($importFile): ?>
                            <div class="mt-3 flex items-center gap-2 p-3 bg-indigo-50 rounded-lg">
                                <svg class="w-5 h-5 text-indigo-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span class="text-sm text-indigo-800 font-medium truncate"><?php echo e($importFile->getClientOriginalName()); ?></span>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['importFile'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 text-xs mt-2"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        
                        <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                            <p class="text-xs font-medium text-gray-700 mb-2">Desteklenen Formatlar:</p>
                            <div class="grid grid-cols-1 gap-2 text-xs text-gray-500">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-orange-100 text-orange-700 font-medium">Trendyol</span>
                                    <span>Dışa aktar → Barkod, fiyat, stok, görseller</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 font-medium">Manuel</span>
                                    <span>Stok Kodu, Ürün Adı, Maliyet, Desi, Durum</span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        
                        <div class="text-center py-4">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($importResult['success']): ?>
                                <div class="inline-flex items-center justify-center w-14 h-14 bg-emerald-100 rounded-full mb-4">
                                    <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <h4 class="text-lg font-semibold text-gray-900 mb-2">İçe Aktarma Tamamlandı!</h4>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($importResult['type']): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium mb-3 <?php echo e($importResult['type'] === 'trendyol' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700'); ?>">
                                        <?php echo e($importResult['type'] === 'trendyol' ? 'Trendyol Listesi' : 'Manuel Liste'); ?>

                                    </span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php else: ?>
                                <div class="inline-flex items-center justify-center w-14 h-14 bg-red-100 rounded-full mb-4">
                                    <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </div>
                                <h4 class="text-lg font-semibold text-gray-900 mb-2">Hata Oluştu</h4>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <p class="text-sm text-gray-600 mb-4"><?php echo e($importResult['message']); ?></p>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($importResult['success']): ?>
                            <div class="grid grid-cols-3 gap-3">
                                <div class="bg-emerald-50 rounded-lg p-3">
                                    <p class="text-2xl font-bold text-emerald-700"><?php echo e($importResult['imported']); ?></p>
                                    <p class="text-xs text-emerald-600">Yeni Eklenen</p>
                                </div>
                                <div class="bg-blue-50 rounded-lg p-3">
                                    <p class="text-2xl font-bold text-blue-700"><?php echo e($importResult['updated']); ?></p>
                                    <p class="text-xs text-blue-600">Güncellenen</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-2xl font-bold text-gray-600"><?php echo e($importResult['skipped']); ?></p>
                                    <p class="text-xs text-gray-500">Atlanan</p>
                                </div>
                            </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($importResult['errors'])): ?>
                            <div class="mt-4 text-left bg-red-50 rounded-lg p-3 max-h-32 overflow-y-auto">
                                <p class="text-xs font-medium text-red-700 mb-1">Hatalar:</p>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = array_slice($importResult['errors'], 0, 5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $err): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                    <p class="text-xs text-red-600"><?php echo e($err); ?></p>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($importResult['errors']) > 5): ?>
                                    <p class="text-xs text-red-400 mt-1">... ve <?php echo e(count($importResult['errors']) - 5); ?> hata daha</p>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t border-gray-100">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$importResult): ?>
                        <button wire:click="importExcel" <?php echo e(!$importFile ? 'disabled' : ''); ?>

                            class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-500 shadow-sm sm:ml-3 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($importing): ?>
                                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                İçe Aktarılıyor...
                            <?php else: ?>
                                İçe Aktar
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <button type="button" wire:click="closeImportModal"
                        class="mt-3 sm:mt-0 w-full sm:w-auto px-4 py-3 sm:py-2 text-sm font-semibold text-gray-900 bg-white rounded-lg ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition-colors">
                        <?php echo e($importResult ? 'Kapat' : 'İptal'); ?>

                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php /**PATH C:\laragon\www\zolm\resources\views/livewire/partials/mp-products-import-modal.blade.php ENDPATH**/ ?>