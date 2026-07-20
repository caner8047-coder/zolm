<div class="w-full space-y-4 lg:space-y-6">
    <style>
        .col-resize-handle { position: absolute; right: 0; top: 0; bottom: 0; width: 4px; cursor: col-resize; background: transparent; z-index: 10; transition: background 0.15s; }
        .col-resize-handle:hover, .col-resize-handle.active { background: #6366f1; }
        .sortable-th { cursor: pointer; user-select: none; position: relative; }
        .sortable-th:hover { background: #f8fafc; }
        #invoiceTable .text-xs { font-size: 11px !important; }
        #invoiceTable .text-sm { font-size: 13px !important; }
        #invoiceTable { table-layout: fixed; width: 100%; }
        #invoiceTable th, #invoiceTable td { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    </style>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('columnResize', () => ({
                resizing: false, startX: 0, startWidth: 0, currentTh: null, handle: null,
                startResize(e, th) {
                    this.resizing = true; this.startX = e.pageX; this.currentTh = th;
                    this.startWidth = th.offsetWidth; this.handle = e.target;
                    this.handle.classList.add('active');
                    document.body.style.cursor = 'col-resize';
                },
                doResize(e) {
                    if (!this.resizing) return;
                    let width = Math.max(40, this.startWidth + (e.pageX - this.startX));
                    this.currentTh.style.width = width + 'px';
                },
                stopResize() {
                    if (this.resizing) {
                        this.resizing = false;
                        if (this.handle) this.handle.classList.remove('active');
                        document.body.style.cursor = '';
                    }
                }
            }));
        });
    </script>

    <!-- Header & Workspace Select -->
    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl lg:text-2xl font-bold text-slate-900">Kargo Faturası Analizi</h1>
                <p class="mt-1 text-sm text-slate-500">Kargo kesintilerini siparişlerinizle eşleştirip net kârlılığınızı tespit edin.</p>
            </div>
            
            <div class="flex flex-col sm:flex-row items-center gap-3">
                <select wire:model.live="selectedStoreId" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:ring-slate-500 w-full sm:w-auto">
                    <option value="0">Mağaza Seçin</option>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </section>

    @if($selectedStoreId)
        <!-- Data Ledger Container -->
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm flex flex-col h-full" x-data="columnResize()" @mousemove.window="doResize" @mouseup.window="stopResize">
            
            <!-- Command Bar -->
            <div class="p-4 lg:p-6 border-b border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4 bg-slate-50/50 rounded-t-[10px]">
                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <!-- Column Visibility Dropdown -->
                    <div x-data="{ open: false }" class="relative inline-block text-left w-full sm:w-auto">
                        <button @click="open = !open" type="button" class="inline-flex w-full sm:w-auto justify-center gap-x-1.5 rounded-[6px] bg-white px-3 py-2 text-sm font-medium text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                            ⚙️ Kolonlar
                        </button>
                        <div x-show="open" @click.outside="open = false" style="display: none;" class="absolute right-0 sm:left-0 z-10 mt-2 w-56 origin-top-right sm:origin-top-left rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                            <div class="py-1" role="none">
                                @foreach(self::$allColumnDefs as $key => $label)
                                    <label class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 cursor-pointer">
                                        <input type="checkbox" wire:click="toggleColumn('{{ $key }}')" 
                                            class="mr-3 rounded border-slate-300 text-slate-600 focus:ring-slate-500" 
                                            {{ in_array($key, $visibleColumns) ? 'checked' : '' }}>
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    
                    <span class="text-xs text-slate-500 whitespace-nowrap">
                        {{ count($visibleColumns) }} / {{ count(self::$allColumnDefs) }} gösteriliyor
                    </span>
                </div>
                
                <div class="flex items-center gap-2">
                    <button class="inline-flex items-center gap-2 rounded-[6px] bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 transition">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Excel İndir
                    </button>
                </div>
            </div>

            <!-- Table Container Desktop -->
            <div class="hidden md:block overflow-x-auto rounded-b-[10px]">
                <table id="invoiceTable" class="min-w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 border-b border-slate-200 text-xs font-medium text-slate-500 uppercase tracking-wider">
                        <tr>
                            @foreach(self::$allColumnDefs as $key => $label)
                                @if(in_array($key, $visibleColumns))
                                    <th class="px-4 py-3 {{ isset(self::$sortableColumns[$key]) ? 'sortable-th' : '' }}" 
                                        @if(isset(self::$sortableColumns[$key])) wire:click="sortTable('{{ $key }}')" @endif>
                                        
                                        <div class="flex items-center justify-between">
                                            <span>{{ $label }}</span>
                                            @if(isset(self::$sortableColumns[$key]))
                                                <span class="text-[10px] text-slate-400 ml-1">
                                                    @if($sortBy === self::$sortableColumns[$key])
                                                        {{ $sortDir === 'asc' ? '▲' : '▼' }}
                                                    @else
                                                        ⇅
                                                    @endif
                                                </span>
                                            @endif
                                        </div>
                                        <div class="col-resize-handle" @mousedown.prevent="startResize($event, $event.target.closest('th'))"></div>
                                    </th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($invoices as $invoice)
                            <tr class="hover:bg-slate-50/50">
                                @if(in_array('invoice_serial_number', $visibleColumns))
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $invoice->invoice_serial_number }}</td>
                                @endif
                                
                                @if(in_array('parcel_unique_id', $visibleColumns))
                                    <td class="px-4 py-3 text-slate-600 font-mono text-xs">{{ $invoice->parcel_unique_id }}</td>
                                @endif

                                @if(in_array('order_number', $visibleColumns))
                                    <td class="px-4 py-3 text-slate-900">{{ $invoice->order_number ?? '-' }}</td>
                                @endif

                                @if(in_array('carrier_code', $visibleColumns))
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 text-xs font-mono rounded bg-indigo-50 text-indigo-700 uppercase">{{ $invoice->carrier_code ?? 'BİLİNMİYOR' }}</span>
                                    </td>
                                @endif

                                @if(in_array('desi', $visibleColumns))
                                    <td class="px-4 py-3 font-mono text-slate-600">{{ number_format($invoice->desi, 2, ',', '.') }}</td>
                                @endif
                                
                                @if(in_array('amount', $visibleColumns))
                                    <td class="px-4 py-3 text-slate-900 font-medium">₺{{ number_format($invoice->total_amount, 2, ',', '.') }}</td>
                                @endif

                                @if(in_array('status', $visibleColumns))
                                    <td class="px-4 py-3">
                                        @if($invoice->status === 'pending')
                                            <span class="px-2 py-0.5 text-xs font-mono rounded bg-amber-50 text-amber-700">BEKLİYOR</span>
                                        @elseif($invoice->status === 'completed')
                                            <span class="px-2 py-0.5 text-xs font-mono rounded bg-emerald-50 text-emerald-700">EŞLEŞTİ</span>
                                        @else
                                            <span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-100 text-slate-700">{{ strtoupper($invoice->status) }}</span>
                                        @endif
                                    </td>
                                @endif

                                @if(in_array('invoice_date', $visibleColumns))
                                    <td class="px-4 py-3 text-xs">{{ $invoice->invoice_date ? $invoice->invoice_date->format('d.m.Y') : '-' }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="100%" class="px-4 py-8 text-center text-slate-500">
                                    Henüz kargo faturası kaydı bulunmuyor. Senkronizasyon çalıştığından emin olun.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <div class="md:hidden divide-y divide-slate-100 p-4 space-y-4">
                @forelse($invoices as $invoice)
                    <div class="rounded-xl border border-slate-200 p-4 bg-white shadow-sm flex flex-col gap-3">
                        <div class="flex justify-between items-start border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-900 text-sm truncate max-w-[200px]">{{ $invoice->invoice_serial_number }}</span>
                            </div>
                            @if($invoice->status === 'pending')
                                <span class="px-2 py-0.5 text-xs font-mono rounded bg-amber-50 text-amber-700 shrink-0">BEKLİYOR</span>
                            @else
                                <span class="px-2 py-0.5 text-xs font-mono rounded bg-emerald-50 text-emerald-700 shrink-0">EŞLEŞTİ</span>
                            @endif
                        </div>
                        
                        <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                            <div class="flex flex-col">
                                <span class="text-xs text-slate-500">Sipariş No</span>
                                <span class="font-medium text-slate-900">{{ $invoice->order_number ?? '-' }}</span>
                            </div>
                            <div class="flex flex-col text-right">
                                <span class="text-xs text-slate-500">Tutar</span>
                                <span class="font-medium text-slate-900">₺{{ number_format($invoice->total_amount, 2, ',', '.') }}</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-xs text-slate-500">Desi</span>
                                <span class="font-medium text-slate-900">{{ number_format($invoice->desi, 2, ',', '.') }}</span>
                            </div>
                            <div class="flex flex-col text-right">
                                <span class="text-xs text-slate-500">Kargo</span>
                                <span class="font-medium text-indigo-700 uppercase">{{ $invoice->carrier_code ?? 'BİLİNMİYOR' }}</span>
                            </div>
                        </div>
                        <div class="pt-2 flex justify-between text-xs text-slate-400">
                            <span class="font-mono">PKT: {{ $invoice->parcel_unique_id }}</span>
                            <span>{{ $invoice->invoice_date ? $invoice->invoice_date->format('d.m.Y') : '-' }}</span>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-slate-500 text-sm">
                        Henüz kargo faturası kaydı bulunmuyor.
                    </div>
                @endforelse
            </div>
            
            <div class="p-4 border-t border-slate-200">
                @if(method_exists($invoices, 'links'))
                    {{ $invoices->links() }}
                @endif
            </div>

        </section>
    @else
        <div class="rounded-[10px] border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
            <p class="text-slate-500">Kargo faturası verilerini görüntülemek için lütfen yukarıdan bir mağaza seçin.</p>
        </div>
    @endif
</div>
