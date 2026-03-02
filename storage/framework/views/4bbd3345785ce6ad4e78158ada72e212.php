<div>
    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
        <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-sm"><?php echo e(session('success')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?>
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-red-800 text-sm"><?php echo e(session('error')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">⚗️ Reçete Hazırlama</h1>
        <p class="text-sm text-gray-500 mt-1">Ham madde stok kartları yönetimi ve reçete oluşturma</p>
    </div>

    
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        <div class="bg-white rounded-xl border p-4 text-center">
            <div class="text-2xl font-bold text-gray-900"><?php echo e($stats['total']); ?></div>
            <div class="text-xs text-gray-500">Toplam Malzeme</div>
        </div>
        <div class="bg-white rounded-xl border p-4 text-center">
            <div class="text-2xl font-bold text-blue-600"><?php echo e($stats['fabric']); ?></div>
            <div class="text-xs text-gray-500">Kumaş</div>
        </div>
        <div class="bg-white rounded-xl border p-4 text-center">
            <div class="text-2xl font-bold text-amber-600"><?php echo e($stats['foam']); ?></div>
            <div class="text-xs text-gray-500">Sünger</div>
        </div>
        <div class="bg-white rounded-xl border p-4 text-center">
            <div class="text-2xl font-bold text-emerald-600"><?php echo e($stats['wood']); ?></div>
            <div class="text-xs text-gray-500">Ahşap</div>
        </div>
        <div class="bg-white rounded-xl border p-4 text-center">
            <div class="text-2xl font-bold text-gray-600"><?php echo e($stats['hardware']); ?></div>
            <div class="text-xs text-gray-500">Hırdavat</div>
        </div>
        <div class="bg-white rounded-xl border p-4 text-center">
            <div class="text-2xl font-bold text-purple-600"><?php echo e($stats['recipes']); ?></div>
            <div class="text-xs text-gray-500">Reçete</div>
        </div>
    </div>

    
    <div class="flex gap-2 mb-4 border-b border-gray-200">
        <button wire:click="$set('activeTab', 'materials')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors <?php echo e($activeTab === 'materials' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700'); ?>">
            📦 Ham Madde Kartları
        </button>
        <button wire:click="$set('activeTab', 'recipes')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors <?php echo e($activeTab === 'recipes' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700'); ?>">
            📋 Reçeteler
        </button>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeTab === 'materials'): ?>
    
    <div class="bg-white rounded-xl border">
        
        <div class="p-4 border-b border-gray-100 flex flex-col sm:flex-row gap-3">
            
            <div class="flex-1">
                <input wire:model.live.debounce.300ms="search" type="text"
                       placeholder="Stok kodu veya malzeme adı ara..."
                       class="w-full px-4 py-2.5 text-sm border rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-gray-900">
            </div>
            
            <select wire:model.live="categoryFilter" class="px-3 py-2.5 text-sm border rounded-lg">
                <option value="all">Tüm Kategoriler</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Models\Material::CATEGORIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <option value="<?php echo e($key); ?>"><?php echo e($label); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>
            <select wire:model.live="statusFilter" class="px-3 py-2.5 text-sm border rounded-lg">
                <option value="active">Aktif</option>
                <option value="inactive">Pasif</option>
                <option value="all">Tümü</option>
            </select>
            
            <div class="flex gap-2">
                <button wire:click="openCreateModal"
                        class="px-4 py-2.5 text-sm font-medium bg-gray-900 text-white rounded-lg hover:bg-gray-800">
                    + Yeni Malzeme
                </button>
                <button wire:click="$set('showImportModal', true)"
                        class="px-4 py-2.5 text-sm font-medium border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    📥 Excel Import
                </button>
            </div>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($selectedMaterials) > 0): ?>
        <div class="px-4 py-3 bg-blue-50 border-b flex items-center gap-3 flex-wrap">
            <span class="text-sm font-medium text-blue-800"><?php echo e(count($selectedMaterials)); ?> malzeme seçili</span>
            <select wire:model="bulkAction" class="px-3 py-1.5 text-sm border rounded-lg">
                <option value="">İşlem seç...</option>
                <option value="activate">Aktifleştir</option>
                <option value="deactivate">Pasife Al</option>
                <option value="change_waste">Fire Değiştir</option>
                <option value="change_category">Kategori Değiştir</option>
                <option value="delete">Sil</option>
            </select>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bulkAction === 'change_waste'): ?>
                <input wire:model="bulkWasteRate" type="number" step="0.01" min="0" max="1" placeholder="Yeni fire (0.10)"
                       class="px-3 py-1.5 text-sm border rounded-lg w-32">
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bulkAction === 'change_category'): ?>
                <select wire:model="bulkCategory" class="px-3 py-1.5 text-sm border rounded-lg">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Models\Material::CATEGORIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <option value="<?php echo e($key); ?>"><?php echo e($label); ?></option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <button wire:click="applyBulkAction" wire:confirm="Seçili malzemelere bu işlemi uygulamak istediğinize emin misiniz?"
                    class="px-3 py-1.5 text-sm font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                Uygula
            </button>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left"><input type="checkbox" wire:model.live="selectAll" class="rounded"></th>
                        <th class="px-4 py-3 text-left">Stok Kodu</th>
                        <th class="px-4 py-3 text-left">Malzeme Adı</th>
                        <th class="px-4 py-3 text-left">Kategori</th>
                        <th class="px-4 py-3 text-center">Birim</th>
                        <th class="px-4 py-3 text-center">Fire %</th>
                        <th class="px-4 py-3 text-center">Kumaş Eni</th>
                        <th class="px-4 py-3 text-center">Fiyat</th>
                        <th class="px-4 py-3 text-center">Durum</th>
                        <th class="px-4 py-3 text-right">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $materials; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $material): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <tr class="hover:bg-gray-50 <?php echo e(!$material->is_active ? 'opacity-50' : ''); ?>">
                        <td class="px-4 py-3">
                            <input type="checkbox" wire:model.live="selectedMaterials" value="<?php echo e($material->id); ?>" class="rounded">
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-700"><?php echo e($material->code); ?></td>
                        <td class="px-4 py-3 font-medium text-gray-900 max-w-xs truncate"><?php echo e($material->name); ?></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 text-xs rounded-full
                                <?php echo e(match($material->category) {
                                    'fabric' => 'bg-blue-100 text-blue-700',
                                    'foam' => 'bg-amber-100 text-amber-700',
                                    'wood' => 'bg-emerald-100 text-emerald-700',
                                    'hardware' => 'bg-gray-100 text-gray-700',
                                    'packaging' => 'bg-purple-100 text-purple-700',
                                    'textile' => 'bg-indigo-100 text-indigo-700',
                                    'lining' => 'bg-pink-100 text-pink-700',
                                    default => 'bg-gray-100 text-gray-600',
                                }); ?>">
                                <?php echo e($material->category_label); ?>

                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-600"><?php echo e($material->unit_label); ?></td>
                        <td class="px-4 py-3 text-center text-gray-600">%<?php echo e(number_format($material->default_waste_rate * 100, 0)); ?></td>
                        <td class="px-4 py-3 text-center text-gray-600">
                            <?php echo e($material->fabric_width_cm ? $material->fabric_width_cm . ' cm' : '—'); ?>

                        </td>
                        <td class="px-4 py-3 text-center text-gray-600">
                            <?php echo e($material->unit_price ? number_format($material->unit_price, 2) . ' ₺' : '—'); ?>

                        </td>
                        <td class="px-4 py-3 text-center">
                            <button wire:click="toggleActive(<?php echo e($material->id); ?>)" class="text-xs">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($material->is_active): ?>
                                    <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded-full">Aktif</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full">Pasif</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </button>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                <button wire:click="editMaterial(<?php echo e($material->id); ?>)"
                                        class="p-1.5 text-gray-400 hover:text-gray-900 rounded" title="Düzenle">
                                    ✏️
                                </button>
                                <button wire:click="deleteMaterial(<?php echo e($material->id); ?>)"
                                        wire:confirm="Bu malzemeyi silmek istediğinize emin misiniz?"
                                        class="p-1.5 text-gray-400 hover:text-red-600 rounded" title="Sil">
                                    🗑️
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <tr>
                        <td colspan="10" class="px-4 py-12 text-center text-gray-400">
                            <div class="text-4xl mb-2">📦</div>
                            <p>Henüz malzeme eklenmedi.</p>
                            <button wire:click="openCreateModal" class="mt-2 text-sm text-blue-600 hover:underline">
                                İlk malzemeyi ekle →
                            </button>
                        </td>
                    </tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t"><?php echo e($materials->links()); ?></div>
    </div>

    <?php else: ?>
    
    <div class="bg-white rounded-xl border">
        <div class="p-4 border-b flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Reçeteler</h2>
            <div class="flex gap-2">
                <button wire:click="openRecipeImportModal"
                        class="px-4 py-2.5 text-sm font-medium border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    📥 Excel'den Reçete Import
                </button>
                <button wire:click="createRecipe"
                        class="px-4 py-2.5 text-sm font-medium bg-gray-900 text-white rounded-lg hover:bg-gray-800">
                    + Yeni Reçete
                </button>
            </div>
        </div>

        <div class="divide-y">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $recipes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $recipe): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
            <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-gray-50">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <h3 class="font-medium text-gray-900"><?php echo e($recipe->name); ?></h3>
                        <span class="px-2 py-0.5 text-xs rounded-full <?php echo e($recipe->status_color); ?>"><?php echo e($recipe->status_label); ?></span>
                        <span class="text-xs text-gray-400"><?php echo e($recipe->version); ?></span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recipe->product): ?>
                            🏷️ <?php echo e($recipe->product->product_name); ?>

                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        · <?php echo e($recipe->lines->count()); ?> satır
                        · <?php echo e($recipe->updated_at->diffForHumans()); ?>

                    </p>
                </div>
                <div class="flex gap-2">
                    <button wire:click="editRecipe(<?php echo e($recipe->id); ?>)"
                            class="px-3 py-1.5 text-sm border rounded-lg hover:bg-gray-50">
                        Düzenle
                    </button>
                    <button wire:click="deleteRecipe(<?php echo e($recipe->id); ?>)"
                            wire:confirm="Bu reçeteyi silmek istediğinize emin misiniz?"
                            class="px-3 py-1.5 text-sm border border-red-200 text-red-600 rounded-lg hover:bg-red-50">
                        Sil
                    </button>
                </div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <div class="p-12 text-center text-gray-400">
                <div class="text-4xl mb-2">📋</div>
                <p>Henüz reçete oluşturulmadı.</p>
                <button wire:click="createRecipe" class="mt-2 text-sm text-blue-600 hover:underline">
                    İlk reçeteyi oluştur →
                </button>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showCreateModal): ?>
    <div class="fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showCreateModal', false)">
        <div class="bg-white rounded-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b">
                <h2 class="text-lg font-bold"><?php echo e($editingMaterialId ? 'Malzeme Düzenle' : 'Yeni Malzeme'); ?></h2>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Stok Kodu *</label>
                        <input wire:model="formCode" type="text" class="w-full px-3 py-2 text-sm border rounded-lg" placeholder="HMKMŞ001">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['formCode'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-xs text-red-500"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Kategori *</label>
                        <select wire:model.live="formCategory" class="w-full px-3 py-2 text-sm border rounded-lg">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Models\Material::CATEGORIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <option value="<?php echo e($key); ?>"><?php echo e($label); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Malzeme Adı *</label>
                    <input wire:model="formName" type="text" class="w-full px-3 py-2 text-sm border rounded-lg" placeholder="HM KUMAŞ DİĞER...">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['formName'];
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
                        <select wire:model="formUnit" class="w-full px-3 py-2 text-sm border rounded-lg">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Models\Material::UNITS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <option value="<?php echo e($key); ?>"><?php echo e($label); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Varsayılan Fire</label>
                        <div class="flex items-center gap-2">
                            <input wire:model="formWasteRate" type="number" step="0.01" min="0" max="1"
                                   class="w-full px-3 py-2 text-sm border rounded-lg" placeholder="0.10">
                            <span class="text-xs text-gray-400 whitespace-nowrap">= %<?php echo e(number_format(($formWasteRate ?? 0) * 100, 0)); ?></span>
                        </div>
                    </div>
                </div>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($formCategory === 'fabric' || $formCategory === 'textile'): ?>
                <div class="p-4 bg-blue-50 rounded-lg space-y-3">
                    <h4 class="text-xs font-semibold text-blue-700 uppercase">Kumaş Ayarları</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-blue-600 mb-1">Kumaş Eni (cm)</label>
                            <input wire:model="formFabricWidth" type="number" step="0.5"
                                   class="w-full px-3 py-2 text-sm border rounded-lg" placeholder="140">
                        </div>
                        <div>
                            <label class="block text-xs text-blue-600 mb-1">Metre Hesap Yöntemi</label>
                            <select wire:model="formFabricMethod" class="w-full px-3 py-2 text-sm border rounded-lg">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Models\Material::FABRIC_METHODS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                    <option value="<?php echo e($key); ?>"><?php echo e($label); ?></option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($formCategory === 'foam'): ?>
                <div class="p-4 bg-amber-50 rounded-lg space-y-3">
                    <h4 class="text-xs font-semibold text-amber-700 uppercase">Sünger Ayarları</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-amber-600 mb-1">Yoğunluk (kg/m³)</label>
                            <input wire:model="formDensity" type="number" step="0.1"
                                   class="w-full px-3 py-2 text-sm border rounded-lg" placeholder="15">
                        </div>
                        <div>
                            <label class="block text-xs text-amber-600 mb-1">Kalınlık (cm)</label>
                            <input wire:model="formThickness" type="number" step="0.5"
                                   class="w-full px-3 py-2 text-sm border rounded-lg" placeholder="10">
                        </div>
                    </div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <div class="grid grid-cols-3 gap-4">
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Birim Fiyat</label>
                        <input wire:model="formUnitPrice" type="number" step="0.01"
                               class="w-full px-3 py-2 text-sm border rounded-lg" placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Para Birimi</label>
                        <select wire:model="formCurrency" class="w-full px-3 py-2 text-sm border rounded-lg">
                            <option value="TRY">₺ TRY</option>
                            <option value="USD">$ USD</option>
                            <option value="EUR">€ EUR</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Tedarikçi</label>
                    <input wire:model="formSupplier" type="text" class="w-full px-3 py-2 text-sm border rounded-lg">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Notlar</label>
                    <textarea wire:model="formNotes" rows="2" class="w-full px-3 py-2 text-sm border rounded-lg"></textarea>
                </div>
            </div>
            <div class="p-6 border-t flex justify-end gap-3">
                <button wire:click="$set('showCreateModal', false)" class="px-4 py-2 text-sm border rounded-lg hover:bg-gray-50">İptal</button>
                <button wire:click="saveMaterial" class="px-4 py-2 text-sm bg-gray-900 text-white rounded-lg hover:bg-gray-800">
                    <?php echo e($editingMaterialId ? 'Güncelle' : 'Kaydet'); ?>

                </button>
            </div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showImportModal): ?>
    <div class="fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showImportModal', false)">
        <div class="bg-white rounded-2xl w-full max-w-md">
            <div class="p-6 border-b">
                <h2 class="text-lg font-bold">📥 Excel Import</h2>
                <p class="text-xs text-gray-500 mt-1">Stok kartları Excel dosyanızı yükleyin (A: Stok Kodu, B: Açıklama)</p>
            </div>
            <div class="p-6">
                <input wire:model="importFile" type="file" accept=".xlsx,.xls"
                       class="w-full px-3 py-2 text-sm border rounded-lg">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['importFile'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-xs text-red-500"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="p-6 border-t flex justify-end gap-3">
                <button wire:click="$set('showImportModal', false)" class="px-4 py-2 text-sm border rounded-lg hover:bg-gray-50">İptal</button>
                <button wire:click="importExcel" class="px-4 py-2 text-sm bg-gray-900 text-white rounded-lg hover:bg-gray-800">
                    Import Et
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showRecipeImportModal): ?>
    <div class="fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showRecipeImportModal', false)">
        <div class="bg-white rounded-2xl w-full max-w-md">
            <div class="p-6 border-b">
                <h2 class="text-lg font-bold">📋 Excel'den Reçete Import</h2>
                <p class="text-xs text-gray-500 mt-1">"ürün ağacı" sayfası olan bir Excel dosyası yükleyin. Eksik malzemeler otomatik oluşturulur.</p>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Reçete Adı *</label>
                    <input wire:model="recipeImportName" type="text" class="w-full px-3 py-2 text-sm border rounded-lg" placeholder="Berjer Alaves Reçetesi">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['recipeImportName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-xs text-red-500"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Excel Dosyası *</label>
                    <input wire:model="recipeImportFile" type="file" accept=".xlsx,.xls"
                           class="w-full px-3 py-2 text-sm border rounded-lg">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['recipeImportFile'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-xs text-red-500"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
            <div class="p-6 border-t flex justify-end gap-3">
                <button wire:click="$set('showRecipeImportModal', false)" class="px-4 py-2 text-sm border rounded-lg hover:bg-gray-50">İptal</button>
                <button wire:click="importRecipeExcel" class="px-4 py-2 text-sm bg-gray-900 text-white rounded-lg hover:bg-gray-800">
                    Import Et
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\laragon\www\zolm\resources\views/livewire/recipe-materials-manager.blade.php ENDPATH**/ ?>