<div class="w-full space-y-4 lg:space-y-6">
    <style>
        .col-resize-handle { position: absolute; right: 0; top: 0; bottom: 0; width: 4px; cursor: col-resize; background: transparent; z-index: 10; transition: background 0.15s; }
        .col-resize-handle:hover, .col-resize-handle.active { background: #6366f1; }
        .sortable-th { cursor: pointer; user-select: none; position: relative; }
        .sortable-th:hover { background: #f8fafc; }
        #reasonsTable .text-xs { font-size: 11px !important; }
        #reasonsTable .text-sm { font-size: 13px !important; }
        #reasonsTable { table-layout: fixed; width: 100%; }
        #reasonsTable th, #reasonsTable td { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
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
                <h1 class="text-xl lg:text-2xl font-bold text-slate-900">İade Nedeni Eşleştirme</h1>
                <p class="mt-1 text-sm text-slate-500">Trendyol iade nedenlerini ZOLM iç iade ve fire hesaplamalarıyla eşleştirin.</p>
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
        <!-- Table Section -->
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm flex flex-col h-full" x-data="columnResize()" @mousemove.window="doResize" @mouseup.window="stopResize">
            
            <!-- Command Bar -->
            <div class="p-4 lg:p-6 border-b border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4 bg-slate-50/50 rounded-t-[10px]">
                <div class="flex items-center gap-3 w-full sm:w-auto">
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
            </div>

            <!-- Table Container Desktop -->
            <div class="hidden md:block overflow-x-auto rounded-b-[10px]">
                <table id="reasonsTable" class="min-w-full text-left text-sm text-slate-600 overflow-visible">
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
                        @forelse($reasons as $reason)
                            <tr class="hover:bg-slate-50/50">
                                @if(in_array('platform_reason_id', $visibleColumns))
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $reason->platform_reason_id }}</td>
                                @endif
                                
                                @if(in_array('name', $visibleColumns))
                                    <td class="px-4 py-3 text-slate-900">{{ $reason->name }}</td>
                                @endif

                                @if(in_array('mapped_zolm_reason_code', $visibleColumns))
                                    <td class="px-4 py-3 text-slate-900 overflow-visible">
                                        <select wire:change="updateMapping({{ $reason->id }}, $event.target.value)" class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-sm text-slate-900 focus:border-slate-500 focus:ring-slate-500 max-w-full">
                                            <option value="">-- Eşleştirilmedi --</option>
                                            @foreach($zolmReasons as $code => $label)
                                                <option value="{{ $code }}" {{ $reason->mapped_zolm_reason_code === $code ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endif

                                @if(in_array('is_active', $visibleColumns))
                                    <td class="px-4 py-3">
                                        @if($reason->is_active)
                                            <span class="px-2 py-0.5 text-xs font-mono rounded bg-emerald-100 text-emerald-700">AKTİF</span>
                                        @else
                                            <span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-100 text-slate-700">PASİF</span>
                                        @endif
                                    </td>
                                @endif

                                @if(in_array('updated_at', $visibleColumns))
                                    <td class="px-4 py-3 text-xs">{{ $reason->updated_at->format('d.m.Y H:i') }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="100%" class="px-4 py-8 text-center text-slate-500">
                                    Henüz İade Nedeni kaydı bulunmuyor. Senkronizasyon çalıştığından emin olun.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <div class="md:hidden divide-y divide-slate-100 p-4 space-y-4">
                @forelse($reasons as $reason)
                    <div class="rounded-xl border border-slate-200 p-4 bg-white shadow-sm flex flex-col gap-3">
                        <div class="flex justify-between items-start border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-900 text-sm truncate max-w-[200px]">{{ $reason->name }}</span>
                            </div>
                            @if($reason->is_active)
                                <span class="px-2 py-0.5 text-xs font-mono rounded bg-emerald-100 text-emerald-700 shrink-0">AKTİF</span>
                            @else
                                <span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-100 text-slate-700 shrink-0">PASİF</span>
                            @endif
                        </div>
                        
                        <div class="flex flex-col gap-2 text-sm">
                            <div class="flex flex-col">
                                <span class="text-xs text-slate-500">ZOLM Eşleştirmesi</span>
                                <select wire:change="updateMapping({{ $reason->id }}, $event.target.value)" class="mt-1 rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-sm text-slate-900 w-full">
                                    <option value="">-- Eşleştirilmedi --</option>
                                    @foreach($zolmReasons as $code => $label)
                                        <option value="{{ $code }}" {{ $reason->mapped_zolm_reason_code === $code ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="pt-2 flex justify-between text-xs text-slate-400">
                            <span>Platform ID: {{ $reason->platform_reason_id }}</span>
                            <span>{{ $reason->updated_at->format('d.m.Y') }}</span>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-slate-500 text-sm">
                        Henüz İade Nedeni kaydı bulunmuyor.
                    </div>
                @endforelse
            </div>
            
            <div class="p-4 border-t border-slate-200">
                @if(method_exists($reasons, 'links'))
                    {{ $reasons->links() }}
                @endif
            </div>

        </section>
    @else
        <div class="rounded-[10px] border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
            <p class="text-slate-500">İade nedenlerini görüntülemek için lütfen yukarıdan bir mağaza seçin.</p>
        </div>
    @endif
</div>
