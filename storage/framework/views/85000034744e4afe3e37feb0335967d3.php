<div>
    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
        <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-sm"><?php echo e(session('success')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?>
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-red-800 text-sm"><?php echo e(session('error')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <div class="flex items-center gap-3">
                <a href="<?php echo e(route('recipe.materials')); ?>" class="text-gray-400 hover:text-gray-900">← Geri</a>
                <h1 class="text-2xl font-bold text-gray-900"><?php echo e($recipeId ? 'Reçete Düzenle' : 'Yeni Reçete'); ?></h1>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($status !== 'draft'): ?>
                    <span class="px-2 py-0.5 text-xs rounded-full <?php echo e($status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'); ?>">
                        <?php echo e(\App\Models\Recipe::STATUSES[$status] ?? $status); ?>

                    </span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
        <div class="flex gap-2">
            <button wire:click="saveRecipe" class="px-4 py-2.5 text-sm font-medium bg-gray-900 text-white rounded-lg hover:bg-gray-800">
                💾 Kaydet
            </button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recipeId): ?>
            <button wire:click="activateRecipe" class="px-4 py-2.5 text-sm font-medium bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">
                ✅ Aktif Et
            </button>
            <button wire:click="duplicateRecipe" class="px-4 py-2.5 text-sm border rounded-lg hover:bg-gray-50">
                📋 Kopyala
            </button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    
    <div class="bg-white rounded-xl border p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Nihai Ürün (Pazaryeri)</label>
                <div class="relative">
                    <input wire:model.live.debounce.300ms="productSearch" type="text"
                           placeholder="Ürün adı veya stok kodu ile arayın..."
                           class="w-full px-3 py-2.5 text-sm border rounded-lg <?php echo e($selectedProductId ? 'bg-emerald-50 border-emerald-300' : ''); ?>">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedProductId): ?>
                        <button wire:click="clearProduct" class="absolute right-2 top-2.5 text-gray-400 hover:text-red-500 text-sm">✕</button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($productResults) > 0): ?>
                    <div class="absolute z-30 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-48 overflow-y-auto">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $productResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <button wire:click="selectProduct(<?php echo e($pr['id']); ?>)"
                                class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 border-b last:border-0">
                            <span class="font-medium"><?php echo e($pr['product_name']); ?></span>
                            <span class="text-xs text-gray-400 ml-2"><?php echo e($pr['stock_code'] ?? ''); ?></span>
                        </button>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Reçete Adı *</label>
                <input wire:model="recipeName" type="text" class="w-full px-3 py-2.5 text-sm border rounded-lg" placeholder="Ürün reçetesi">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['recipeName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-xs text-red-500"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Versiyon</label>
                <input wire:model="version" type="text" class="w-full px-3 py-2.5 text-sm border rounded-lg" placeholder="v1">
            </div>
        </div>
    </div>

    
    <div class="flex gap-2 mb-4 border-b border-gray-200">
        <button wire:click="$set('viewMode', 'all')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors <?php echo e($viewMode === 'all' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700'); ?>">
            Tüm Satırlar (<?php echo e(count($lines)); ?>)
        </button>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Models\RecipeLine::OPERATIONS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opKey => $opLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
            <?php $opCount = collect($lines)->where('operation', $opKey)->count(); ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($opCount > 0): ?>
            <button wire:click="$set('viewMode', 'all')"
                    class="px-3 py-2.5 text-xs font-medium border-b-2 border-transparent text-gray-400">
                <?php echo e($opLabel); ?> (<?php echo e($opCount); ?>)
            </button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <button wire:click="$set('viewMode', 'bom')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors <?php echo e($viewMode === 'bom' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700'); ?>">
            📊 Konsolide BOM
        </button>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($viewMode === 'bom' && $recipeId): ?>
    
    <div class="bg-white rounded-xl border overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Stok Kodu</th>
                    <th class="px-4 py-3 text-left">Malzeme</th>
                    <th class="px-4 py-3 text-center">Toplam Miktar</th>
                    <th class="px-4 py-3 text-center">Birim</th>
                    <th class="px-4 py-3 text-left">Operasyonlar</th>
                    <th class="px-4 py-3 text-right">Maliyet</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->consolidatedBom; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bom): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs"><?php echo e($bom['material_code']); ?></td>
                    <td class="px-4 py-3 font-medium"><?php echo e($bom['material_name']); ?></td>
                    <td class="px-4 py-3 text-center font-bold"><?php echo e(number_format($bom['total_qty'], 4)); ?></td>
                    <td class="px-4 py-3 text-center text-gray-500"><?php echo e(\App\Models\Material::UNITS[$bom['unit']] ?? $bom['unit']); ?></td>
                    <td class="px-4 py-3">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $bom['operations']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $op): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                            <span class="px-1.5 py-0.5 text-xs bg-gray-100 rounded mr-1"><?php echo e(\App\Models\RecipeLine::OPERATIONS[$op] ?? $op); ?></span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bom['unit_price']): ?>
                            <?php echo e(number_format($bom['total_cost'], 2)); ?> ₺
                        <?php else: ?>
                            <span class="text-gray-300">—</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                </tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    
    <div class="bg-white rounded-xl border">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-3 py-3 text-left w-24">Operasyon</th>
                        <th class="px-3 py-3 text-left w-28">Kullanılan Yer</th>
                        <th class="px-3 py-3 text-left" style="min-width:200px">Malzeme</th>
                        <th class="px-3 py-3 text-left w-28">Hesap Tipi</th>
                        <th class="px-3 py-3 text-center w-16">En</th>
                        <th class="px-3 py-3 text-center w-16">Boy</th>
                        <th class="px-3 py-3 text-center w-16">Yük.</th>
                        <th class="px-3 py-3 text-center w-16">Adet</th>
                        <th class="px-3 py-3 text-center w-16">Fire</th>
                        <th class="px-3 py-3 text-center w-24">Sonuç</th>
                        <th class="px-3 py-3 text-center w-20">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $lines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <tr class="hover:bg-gray-50 group" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processElementKey('line-{{ $i }}', get_defined_vars()); ?>wire:key="line-<?php echo e($i); ?>">
                        
                        <td class="px-3 py-2">
                            <select wire:model.live="lines.<?php echo e($i); ?>.operation" class="w-full px-2 py-1.5 text-xs border rounded">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Models\RecipeLine::OPERATIONS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                    <option value="<?php echo e($k); ?>"><?php echo e($v); ?></option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </select>
                        </td>
                        
                        <td class="px-3 py-2">
                            <input wire:model.lazy="lines.<?php echo e($i); ?>.usage_area" type="text"
                                   class="w-full px-2 py-1.5 text-xs border rounded" placeholder="oturum, sırt...">
                        </td>
                        
                        <td class="px-3 py-2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($line['material_id']): ?>
                                <div class="flex items-center gap-1">
                                    <span class="text-xs truncate flex-1" title="<?php echo e($line['material_label']); ?>"><?php echo e($line['material_label']); ?></span>
                                    <button wire:click="$set('lines.<?php echo e($i); ?>.material_id', null)" class="text-gray-400 hover:text-red-500 text-xs flex-shrink-0">✕</button>
                                </div>
                            <?php else: ?>
                                <div class="relative">
                                    <input wire:keyup.debounce.300ms="searchMaterial(<?php echo e($i); ?>, $event.target.value)" type="text"
                                           class="w-full px-2 py-1.5 text-xs border rounded" placeholder="Malzeme ara...">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($searchingLineIndex === $i && count($materialSearchResults) > 0): ?>
                                    <div class="absolute z-30 w-64 mt-1 bg-white border rounded-lg shadow-lg max-h-40 overflow-y-auto">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $materialSearchResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <button wire:click="selectMaterial(<?php echo e($i); ?>, <?php echo e($mr['id']); ?>)"
                                                class="w-full text-left px-3 py-2 text-xs hover:bg-gray-50 border-b last:border-0">
                                            <span class="font-mono text-gray-500"><?php echo e($mr['code']); ?></span>
                                            <span class="ml-1"><?php echo e(\Illuminate\Support\Str::limit($mr['name'], 30)); ?></span>
                                        </button>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        <button wire:click="openQuickMaterialModal(<?php echo e($i); ?>)"
                                                class="w-full text-left px-3 py-2 text-xs text-blue-600 hover:bg-blue-50 font-medium">
                                            + Yeni Malzeme Oluştur
                                        </button>
                                    </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        
                        <td class="px-3 py-2">
                            <select wire:model.live="lines.<?php echo e($i); ?>.calc_type" class="w-full px-2 py-1.5 text-xs border rounded">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Models\RecipeLine::CALC_TYPES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                    <option value="<?php echo e($k); ?>"><?php echo e($v); ?></option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </select>
                        </td>
                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($line['calc_type'] === 'fixed_qty'): ?>
                        <td class="px-3 py-2" colspan="4">
                            <input wire:model.live.debounce.500ms="lines.<?php echo e($i); ?>.constant_qty" type="number" step="0.01"
                                   class="w-24 px-2 py-1.5 text-xs border rounded" placeholder="Miktar">
                        </td>
                        <?php else: ?>
                        <td class="px-3 py-2">
                            <input wire:model.live.debounce.500ms="lines.<?php echo e($i); ?>.width_cm" type="number" step="0.1"
                                   class="w-full px-2 py-1.5 text-xs border rounded text-center" placeholder="cm">
                        </td>
                        <td class="px-3 py-2">
                            <input wire:model.live.debounce.500ms="lines.<?php echo e($i); ?>.length_cm" type="number" step="0.1"
                                   class="w-full px-2 py-1.5 text-xs border rounded text-center" placeholder="cm">
                        </td>
                        <td class="px-3 py-2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($line['calc_type'] === 'volume_m3'): ?>
                            <input wire:model.live.debounce.500ms="lines.<?php echo e($i); ?>.height_cm" type="number" step="0.1"
                                   class="w-full px-2 py-1.5 text-xs border rounded text-center" placeholder="cm">
                            <?php else: ?>
                            <span class="text-gray-300 text-xs">—</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        <td class="px-3 py-2">
                            <input wire:model.live.debounce.500ms="lines.<?php echo e($i); ?>.pieces" type="number" step="0.01" min="0"
                                   class="w-full px-2 py-1.5 text-xs border rounded text-center" placeholder="1">
                        </td>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        
                        <td class="px-3 py-2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($line['calc_type'] !== 'fixed_qty'): ?>
                            <input wire:model.live.debounce.500ms="lines.<?php echo e($i); ?>.waste_rate_override" type="number" step="0.01" min="0" max="1"
                                   class="w-full px-2 py-1.5 text-xs border rounded text-center" placeholder="auto">
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        
                        <td class="px-3 py-2 text-center">
                            <div class="font-bold text-gray-900 text-xs" title="Hesaplanan miktar">
                                <?php echo e(number_format($line['calculated_qty'] ?? 0, 4)); ?>

                            </div>
                            <div class="text-[10px] text-gray-400">
                                <?php echo e(\App\Models\Material::UNITS[$line['calculated_unit'] ?? 'pcs'] ?? $line['calculated_unit'] ?? ''); ?>

                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($calculationResults[$i])): ?>
                            <div class="text-[9px] text-blue-400 cursor-help" title="<?php echo e(json_encode($calculationResults[$i])); ?>">
                                📐 debug
                            </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        
                        <td class="px-3 py-2 text-center">
                            <div class="flex justify-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button wire:click="duplicateLine(<?php echo e($i); ?>)" class="p-1 text-gray-400 hover:text-blue-600" title="Kopyala">📋</button>
                                <button wire:click="moveLine(<?php echo e($i); ?>, 'up')" class="p-1 text-gray-400 hover:text-gray-900" title="Yukarı">↑</button>
                                <button wire:click="moveLine(<?php echo e($i); ?>, 'down')" class="p-1 text-gray-400 hover:text-gray-900" title="Aşağı">↓</button>
                                <button wire:click="removeLine(<?php echo e($i); ?>)" class="p-1 text-gray-400 hover:text-red-600" title="Sil">🗑️</button>
                            </div>
                        </td>
                    </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <tr>
                        <td colspan="11" class="px-4 py-12 text-center text-gray-400">
                            <div class="text-4xl mb-2">📝</div>
                            <p>Henüz satır eklenmedi.</p>
                        </td>
                    </tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>

        
        <div class="px-4 py-3 border-t bg-gray-50 flex items-center justify-between">
            <button wire:click="addLine" class="px-4 py-2 text-sm font-medium bg-white border rounded-lg hover:bg-gray-50">
                + Satır Ekle
            </button>
            <div class="flex items-center gap-4 text-xs text-gray-500">
                <span><?php echo e(count($lines)); ?> satır</span>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($lines) > 0): ?>
                <button wire:click="recalculateAll" class="text-blue-600 hover:underline">🔄 Tümünü Hesapla</button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showQuickMaterialModal): ?>
    <div class="fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showQuickMaterialModal', false)">
        <div class="bg-white rounded-2xl w-full max-w-md">
            <div class="p-6 border-b">
                <h2 class="text-lg font-bold">⚡ Hızlı Malzeme Oluştur</h2>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Stok Kodu *</label>
                        <input wire:model="qmCode" type="text" class="w-full px-3 py-2 text-sm border rounded-lg">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['qmCode'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-xs text-red-500"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Kategori</label>
                        <select wire:model.live="qmCategory" class="w-full px-3 py-2 text-sm border rounded-lg">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Models\Material::CATEGORIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <option value="<?php echo e($k); ?>"><?php echo e($v); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Malzeme Adı *</label>
                    <input wire:model="qmName" type="text" class="w-full px-3 py-2 text-sm border rounded-lg">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['qmName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-xs text-red-500"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Birim</label>
                        <select wire:model="qmUnit" class="w-full px-3 py-2 text-sm border rounded-lg">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Models\Material::UNITS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <option value="<?php echo e($k); ?>"><?php echo e($v); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Fire</label>
                        <input wire:model="qmWasteRate" type="number" step="0.01" min="0" max="1"
                               class="w-full px-3 py-2 text-sm border rounded-lg">
                    </div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($qmCategory === 'fabric'): ?>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Kumaş Eni (cm)</label>
                    <input wire:model="qmFabricWidth" type="number" class="w-full px-3 py-2 text-sm border rounded-lg" placeholder="140">
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="p-6 border-t flex justify-end gap-3">
                <button wire:click="$set('showQuickMaterialModal', false)" class="px-4 py-2 text-sm border rounded-lg hover:bg-gray-50">İptal</button>
                <button wire:click="saveQuickMaterial" class="px-4 py-2 text-sm bg-gray-900 text-white rounded-lg hover:bg-gray-800">Kaydet & Ata</button>
            </div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\laragon\www\zolm\resources\views/livewire/recipe-builder.blade.php ENDPATH**/ ?>