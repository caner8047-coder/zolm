<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showOrderModal && !empty($selectedOrderDetails)): ?>
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        
        
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" 
                 aria-hidden="true" 
                 wire:click="closeOrderModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            
            <div class="inline-block align-bottom bg-gray-50 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl w-full border border-gray-200"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">

                <?php
                    $order = $selectedOrderDetails;
                    $basic = $order['basic'];
                    $fin   = $order['financials'];
                    $set   = $order['settlement'];
                    $sum   = $order['summary'];
                ?>

                <!-- HEADER -->
                <div class="bg-white border-b border-gray-200 px-6 py-5 flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h3 class="text-xl leading-6 font-bold text-gray-900" id="modal-title">
                                Sipariş Detayı: #<?php echo e($basic['order_number']); ?>

                            </h3>
                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-<?php echo e($basic['status_color']); ?>-100 text-<?php echo e($basic['status_color']); ?>-800 border border-<?php echo e($basic['status_color']); ?>-200">
                                <?php echo e($basic['status']); ?>

                            </span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($basic['is_flagged']): ?>
                                <span class="px-2 py-1 flex items-center gap-1 text-xs font-bold bg-red-100 text-red-700 rounded-lg border border-red-200 animate-pulse">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                                    Riskli İşlem
                                </span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <p class="text-sm text-gray-500 line-clamp-1 font-medium mt-1">
                            <span class="px-2 py-0.5 bg-gray-100 rounded text-gray-700 mr-2"><?php echo e($basic['quantity'] ?? 1); ?> Adet</span>
                            <?php echo e($basic['product_name'] ?: 'Ürün Bilgisi Excel\'de Yok (Sadece Finansal Kayıt)'); ?>

                        </p>
                        <p class="text-xs text-gray-400 mt-1">
                            Barkod: <span class="font-mono"><?php echo e($basic['barcode'] ?: 'Belirtilmedi'); ?></span> 
                            | Stok Kodu: <span class="font-mono"><?php echo e($basic['stock_code'] ?: 'Belirtilmedi'); ?></span>
                        </p>
                    </div>
                    <button wire:click="closeOrderModal" type="button" class="bg-white rounded-md text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 transition-colors">
                        <span class="sr-only">Kapat</span>
                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-6 pb-8 overflow-y-auto max-h-[75vh]">
                    
                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($basic['status'] === 'İade Edildi' || $basic['status'] === 'İptal Edildi'): ?>
                    <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg shadow-sm flex items-start gap-3">
                        <div class="mt-0.5 text-red-500">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-red-800">Mali / Muhasebe Uyarısı: E-Arşiv & Fatura İptali Uyarısı</h4>
                            <p class="text-xs text-red-700 mt-1">
                                Bu sipariş iptal / iade edilmiştir. Müşteriye kesilen satış faturasının <strong>e-Arşiv veya GİB portalı üzerinden İPTAL edildiğinden</strong> (veya Gider Pusulası düzenlendiğinden) emin olunuz. Aksi takdirde gerçekleşmeyen satışın KDV ve Gelir Vergisini ödemek zorunda kalırsınız!
                            </p>
                        </div>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        
                        
                        <div class="lg:col-span-2 space-y-6">
                            
                            <!-- 1. ZAMAN ÇİZELGESİ (TIMELINE) -->
                            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 border-b pb-2">Paranın Yolculuğu (Zaman Çizelgesi)</h4>
                                <div class="relative">
                                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                                        <div class="w-full border-t-2 border-dashed border-gray-200"></div>
                                    </div>
                                    <div class="relative flex justify-between">
                                        
                                        <!-- Sipariş Alındı -->
                                        <div>
                                            <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center ring-4 ring-white shadow text-blue-600">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                                            </div>
                                            <div class="mt-3 hidden sm:block">
                                                <span class="text-xs font-semibold text-gray-900 block">Sipariş Alındı</span>
                                                <span class="text-xs text-gray-500"><?php echo e($basic['order_date'] ?? 'Belirsiz'); ?></span>
                                            </div>
                                        </div>

                                        <!-- Teslim Edildi -->
                                        <div>
                                            <div class="h-8 w-8 rounded-full <?php echo e(($basic['delivery_date'] || $basic['status'] === 'Teslim Edildi') ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400'); ?> flex items-center justify-center ring-4 ring-white shadow">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            </div>
                                            <div class="mt-3 hidden sm:block text-center">
                                                <span class="text-xs font-semibold text-gray-900 block">Teslimat</span>
                                                <span class="text-xs text-gray-500">
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($basic['delivery_date']): ?>
                                                        <?php echo e($basic['delivery_date']); ?>

                                                    <?php elseif($basic['status'] === 'Teslim Edildi'): ?>
                                                        Tarih Yok
                                                    <?php else: ?>
                                                        Bekliyor
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Vade/Hakediş -->
                                        <div>
                                            <div class="h-8 w-8 rounded-full <?php echo e($set ? ($set['due_date'] ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-400') : 'bg-gray-100 text-gray-400'); ?> flex items-center justify-center ring-4 ring-white shadow" title="<?php echo e(!$set ? 'Ödeme Detay Exceli Yüklenmemiş' : ''); ?>">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            </div>
                                            <div class="mt-3 hidden sm:block text-center">
                                                <span class="text-xs font-semibold text-gray-900 block">Vade Tarihi</span>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($set): ?>
                                                    <span class="text-xs text-gray-500"><?php echo e($set['due_date'] ?? 'Hesaplanmadı'); ?></span>
                                                <?php else: ?>
                                                    <span class="text-[10px] text-red-500 block leading-tight mt-1" title="Vade tarihini görmek için Ödeme Detay Excel'ini sisteme yükleyin.">Ödeme Excel'i<br>Eksik</span>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Bankaya Yatan -->
                                        <div>
                                            <div class="h-8 w-8 rounded-full <?php echo e($set && $set['settlement_date'] ? 'bg-emerald-500 text-white' : 'bg-gray-100 text-gray-400'); ?> flex items-center justify-center ring-4 ring-white shadow" title="<?php echo e(!$set ? 'Ödeme Detay Exceli Yüklenmemiş' : ''); ?>">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </div>
                                            <div class="mt-3 hidden sm:block text-right">
                                                <span class="text-xs font-semibold text-gray-900 block">Tahsil Edildi</span>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($set): ?>
                                                    <span class="text-xs text-gray-500"><?php echo e($set['settlement_date'] ?? 'Henüz Yatmadı'); ?></span>
                                                <?php else: ?>
                                                    <span class="text-[10px] text-red-500 block leading-tight mt-1" title="Ödeme gününü görmek için Ödeme Detay Excel'ini yükleyin.">Ödeme Excel'i<br>Eksik</span>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <!-- 2. KESİNTİ AĞACI (FINANCIAL BREAKDOWN) -->
                            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 border-b pb-2 flex justify-between">
                                    <span>Pazaryeri Kesintileri (Temel)</span>
                                    <span class="text-gray-900 font-bold"><?php echo e(number_format($fin['gross_amount'], 2, ',', '.')); ?> ₺ (Brüt Satış)</span>
                                </h4>
                                
                                <div class="space-y-3">
                                    <!-- Ana Kalemler -->
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fin['discount_amount'] > 0 || $fin['campaign_discount'] > 0): ?>
                                        <div class="pb-2 border-b border-gray-100 space-y-2">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fin['discount_amount'] > 0): ?>
                                            <div class="flex justify-between items-center">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-2 h-2 rounded-full bg-blue-400"></div>
                                                    <span class="text-sm font-medium text-gray-700">Satıcı İndirimi</span>
                                                </div>
                                                <span class="text-sm font-semibold text-blue-600">-<?php echo e(number_format($fin['discount_amount'], 2, ',', '.')); ?> ₺</span>
                                            </div>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fin['campaign_discount'] > 0): ?>
                                            <div class="flex justify-between items-center">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-2 h-2 rounded-full bg-indigo-400"></div>
                                                    <span class="text-sm font-medium text-gray-700">Kampanya İndirimi</span>
                                                </div>
                                                <span class="text-sm font-semibold text-indigo-600">-<?php echo e(number_format($fin['campaign_discount'], 2, ',', '.')); ?> ₺</span>
                                            </div>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full bg-red-400"></div>
                                            <span class="text-sm font-medium text-gray-700">Komisyon Bedeli</span>
                                        </div>
                                        <span class="text-sm font-semibold text-red-600">-<?php echo e(number_format($fin['commission_amount'], 2, ',', '.')); ?> ₺</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full bg-orange-400"></div>
                                            <span class="text-sm font-medium text-gray-700">Kargo Kesintisi <span class="text-xs text-gray-400 font-normal ml-1">(<?php echo e($fin['cargo_company'] ?? 'Sistem/Firma Belirsiz'); ?>)</span></span>
                                        </div>
                                        <span class="text-sm font-semibold text-orange-600">-<?php echo e(number_format($fin['cargo_amount'], 2, ',', '.')); ?> ₺</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full bg-yellow-500"></div>
                                            <span class="text-sm font-medium text-gray-700">Hizmet / İşlem Bedeli</span>
                                        </div>
                                        <span class="text-sm font-semibold text-yellow-600">-<?php echo e(number_format($fin['service_fee'], 2, ',', '.')); ?> ₺</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full bg-purple-400"></div>
                                            <span class="text-sm font-medium text-gray-700">E-Ticaret Stopajı</span>
                                        </div>
                                        <span class="text-sm font-semibold text-purple-600">-<?php echo e(number_format($fin['withholding_tax'], 2, ',', '.')); ?> ₺</span>
                                    </div>

                                    <!-- EKSTRA CEZALAR VE İADELER (Transactions'tan beslenir) -->
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($order['extra_deductions']) > 0 || count($order['refunds']) > 0): ?>
                                        <div class="mt-6 pt-4 border-t-2 border-dashed border-gray-200">
                                            <h5 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Ekstra Cezalar ve İadeler (Cari'den)</h5>
                                            
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $order['extra_deductions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                                <div class="flex justify-between items-center py-2 bg-red-50 px-3 rounded-lg border border-red-100 mb-2">
                                                    <div>
                                                        <span class="text-sm font-bold text-red-800"><?php echo e($tx['type']); ?></span><br>
                                                        <span class="text-xs text-red-600"><?php echo e($tx['desc']); ?> (<?php echo e($tx['date']); ?>)</span>
                                                    </div>
                                                    <span class="text-sm font-bold text-red-700">-<?php echo e(number_format($tx['amount'], 2, ',', '.')); ?> ₺</span>
                                                </div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $order['refunds']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                                <div class="flex justify-between items-center py-2 bg-green-50 px-3 rounded-lg border border-green-100 mb-2">
                                                    <div>
                                                        <span class="text-sm font-bold text-green-800"><?php echo e($tx['type']); ?> (Geri Alım)</span><br>
                                                        <span class="text-xs text-green-600"><?php echo e($tx['desc']); ?> (<?php echo e($tx['date']); ?>)</span>
                                                    </div>
                                                    <span class="text-sm font-bold text-green-700">+<?php echo e(number_format($tx['amount'], 2, ',', '.')); ?> ₺</span>
                                                </div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                </div>

                                <!-- Alt Toplam Sınırı -->
                                <div class="mt-5 bg-gray-50 p-4 rounded-lg border border-gray-200 flex justify-between items-center">
                                    <span class="text-sm font-bold text-gray-700">Tahmini Net Hakediş (Trendyol Beyanı):</span>
                                    <span class="text-lg font-black text-gray-900"><?php echo e(number_format($fin['expected_net'], 2, ',', '.')); ?> ₺</span>
                                </div>
                            </div>
                            
                            <!-- 3. DENETİM BİLDİRİMLERİ (Audit Logs) -->
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($order['audits']) > 0): ?>
                                <div class="bg-red-50 p-5 rounded-xl border border-red-200 shadow-sm">
                                    <h4 class="text-xs font-bold text-red-800 uppercase tracking-wider mb-3 flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                        Denetim Motoru Bulguları
                                    </h4>
                                    <ul class="space-y-3">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $order['audits']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $audit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                            <li class="bg-white p-3 rounded shadow-sm border-l-4 <?php echo e($audit['severity'] === 'critical' ? 'border-red-600' : 'border-orange-400'); ?>">
                                                <p class="text-sm font-bold text-gray-900"><?php echo e($audit['title']); ?></p>
                                                <p class="text-xs text-gray-600 mt-1"><?php echo e($audit['description']); ?></p>
                                            </li>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        </div>

                        
                        <div class="space-y-6">
                            
                            <!-- A. BANKA TAHSİLATI (GERÇEKLEŞEN) -->
                            <div class="bg-gradient-to-br from-gray-900 to-gray-800 p-5 rounded-xl shadow-lg border border-gray-700 text-white relative overflow-hidden">
                                <!-- Dekoratif arka plan -->
                                <div class="absolute -right-6 -top-6 opacity-10">
                                    <svg class="h-32 w-32" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path></svg>
                                </div>
                                
                                <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 z-10 relative">Banka Tahsilatı (Gerçekleşen)</h4>
                                
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($set && $set['settlement_date']): ?>
                                    <div class="flex items-baseline gap-1 my-3 z-10 relative">
                                        <span class="text-4xl font-black text-emerald-400"><?php echo e(number_format($set['seller_hakedis'], 2, ',', '.')); ?></span>
                                        <span class="text-lg font-medium text-emerald-200">₺</span>
                                    </div>
                                    <div class="text-sm text-gray-300 z-10 relative">
                                        Kayıt: <strong><?php echo e($set['settlement_date']); ?></strong>
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($set['is_reconciled']): ?>
                                        <div class="mt-4 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 text-xs font-medium z-10 relative">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            Trendyol raporu ile mutabık
                                        </div>
                                    <?php else: ?>
                                        <div class="mt-4 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-red-500/20 border border-red-500/30 text-rose-300 text-xs font-medium z-10 relative">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                            Eksik Ödeme!
                                        </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php else: ?>
                                    <div class="py-6 text-center z-10 relative">
                                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-700 text-gray-400 mb-3">
                                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        </div>
                                        <p class="text-gray-300 text-sm font-medium">Banka Kaydı Yok / Bekliyor</p>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            <!-- B. NİHAİ KÂR / ZARAR (ZOLM METRİKLERİ) -->
                            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 border-b pb-2">Gerçek Kârlılık Özeti</h4>
                                
                                <div class="space-y-4">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium text-gray-600">
                                            Ürün Maliyeti (COGS)
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($basic['quantity'] ?? 1) > 1): ?>
                                                <span class="text-xs text-gray-400 font-normal ml-1">(<?php echo e($basic['quantity']); ?> adet)</span>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </span>
                                        <span class="text-sm font-bold text-gray-900"><?php echo e(number_format($sum['cost_of_goods'], 2, ',', '.')); ?> ₺</span>
                                    </div>
                                    
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sum['vat_advantage'] > 0): ?>
                                        <div class="flex justify-between items-center text-green-700 bg-green-50 p-2 rounded">
                                            <span class="text-xs font-semibold">+ KDV Avantajı</span>
                                            <span class="text-xs border border-green-200 bg-white px-2 rounded"><?php echo e(number_format($sum['vat_advantage'], 2, ',', '.')); ?> ₺</span>
                                        </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order['return_loss_sum'] > 0): ?>
                                        <div class="flex flex-col text-red-700 bg-red-50 p-2 rounded">
                                            <div class="flex justify-between items-center mb-1">
                                                <span class="text-xs font-semibold">İade Lojistik Zararı</span>
                                                <span class="text-xs font-bold"><?php echo e(number_format($order['return_loss_sum'], 2, ',', '.')); ?> ₺</span>
                                            </div>
                                            <span class="text-[10px] opacity-75">Gidiş kargo bedeli + Dönüş kargo faturası</span>
                                        </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                    <div class="pt-3 border-t-2 border-dashed border-gray-200">
                                        <div class="flex justify-between items-end">
                                            <span class="text-sm font-bold text-gray-900">Net Kâr Durumu</span>
                                            <span class="text-2xl font-black <?php echo e($sum['is_loss'] ? 'text-red-600' : 'text-green-600'); ?>">
                                                <?php echo e($sum['is_loss'] ? '-' : '+'); ?><?php echo e(number_format(abs($sum['absolute_net_profit']), 2, ',', '.')); ?> ₺
                                            </span>
                                        </div>
                                        
                                        
                                        <div class="mt-4 pt-4 border-t border-gray-100">
                                            <h5 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-3">Kâr Motoru Matematiği</h5>
                                            <div class="space-y-2.5 text-sm">
                                                
                                                <div class="flex justify-between items-center text-gray-700">
                                                    <span class="flex items-center gap-2">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                                        Başlangıç (Hakediş)
                                                    </span>
                                                    <span class="font-semibold"><?php echo e(number_format($sum['base_revenue'], 2, ',', '.')); ?> ₺</span>
                                                </div>

                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($sum['stopaj_deduction']) && $sum['stopaj_deduction'] > 0): ?>
                                                <div class="flex justify-between items-center text-gray-500">
                                                    <span class="flex items-center gap-2">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                                                        E-Ticaret Stopajı
                                                    </span>
                                                    <span class="text-red-500 font-medium">-<?php echo e(number_format($sum['stopaj_deduction'], 2, ',', '.')); ?> ₺</span>
                                                </div>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($sum['vat_payable']) && $sum['vat_payable'] > 0): ?>
                                                <div class="flex justify-between items-center text-gray-500">
                                                    <span class="flex items-center gap-2">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                                                        Net KDV Yükü
                                                    </span>
                                                    <span class="text-red-500 font-medium">-<?php echo e(number_format($sum['vat_payable'], 2, ',', '.')); ?> ₺</span>
                                                </div>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($sum['vat_advantage']) && $sum['vat_advantage'] > 0): ?>
                                                <div class="flex justify-between items-center text-gray-600">
                                                    <span class="flex items-center gap-2">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                                                        KDV Avantajı
                                                    </span>
                                                    <span class="text-green-600 font-medium">+<?php echo e(number_format($sum['vat_advantage'], 2, ',', '.')); ?> ₺</span>
                                                </div>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                
                                                <?php $toplamMaliyet = $sum['cost_of_goods'] + $sum['total_extra_debt']; ?>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($toplamMaliyet > 0): ?>
                                                <div class="flex justify-between items-center text-gray-500">
                                                    <span class="flex items-center gap-2">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                                                        Ürün & Ek Maliyetler
                                                    </span>
                                                    <span class="text-red-500 font-medium">-<?php echo e(number_format($toplamMaliyet, 2, ',', '.')); ?> ₺</span>
                                                </div>
                                                <?php else: ?>
                                                <div class="flex justify-between items-center text-gray-400">
                                                    <span class="flex items-center gap-2 shrink-0">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                                                        Ürün & Ek Maliyetler
                                                    </span>
                                                    <span class="font-medium">0,00 ₺</span>
                                                </div>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                            </div>
                                        </div>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sum['is_loss']): ?>
                                            <p class="text-[11px] text-red-500 font-medium text-right mt-2">Bu siparişten zarar ettiğiniz tespit edilmiştir.</p>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- FOOTER -->
                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex flex-row-reverse">
                    <button type="button" wire:click="closeOrderModal" class="w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-5 py-2.5 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto transition-colors">
                        Kapat
                    </button>
                    <!-- TODO: PDF İndir Butonu Buraya Gelebilir -->
                </div>

            </div>
        </div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\laragon\www\zolm\resources\views/livewire/mp-order-modal.blade.php ENDPATH**/ ?>