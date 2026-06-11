@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $sourceLabel = fn (?string $source) => $this->sourceOptions()[$source ?? ''] ?? match ($source) {
        'marketplace_orders' => 'Sipariş',
        'marketplace_questions' => 'Soru',
        'returns' => 'İade',
        'marketplace_claims' => 'Pazaryeri İade',
        'cargo_reports' => 'Kargo',
        'supply_reports' => 'Tedarik',
        'marketplace_finance' => 'Finans',
        'crm_customer_ledger' => 'Müşteri Cari',
        'crm' => 'CRM',
        'order_customer' => 'Sipariş',
        'question_customer' => 'Soru',
        'return_customer' => 'İade',
        'cargo_customer' => 'Kargo',
        'supply_customer' => 'Tedarik',
        default => $source ?: 'Kaynak yok',
    };
    $eventLabel = fn (?string $type) => match ($type) {
        'order' => 'Sipariş',
        'question' => 'Soru',
        'return' => 'İade',
        'cargo' => 'Kargo',
        'supply' => 'Tedarik',
        'note' => 'Not',
        default => $type ?: 'Olay',
    };
    $eventTone = fn (?string $type) => match ($type) {
        'order' => 'border-sky-200 bg-sky-50 text-sky-700',
        'question' => 'border-indigo-200 bg-indigo-50 text-indigo-700',
        'return' => 'border-amber-200 bg-amber-50 text-amber-700',
        'cargo' => 'border-rose-200 bg-rose-50 text-rose-700',
        'supply' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'note' => 'border-slate-200 bg-slate-50 text-slate-700',
        default => 'border-slate-200 bg-white text-slate-600',
    };
    $caseTone = fn (?string $priority) => match ($priority) {
        'critical', 'high' => 'border-rose-200 bg-rose-50 text-rose-700',
        'normal' => 'border-amber-200 bg-amber-50 text-amber-700',
        'low' => 'border-sky-200 bg-sky-50 text-sky-700',
        default => 'border-slate-200 bg-slate-50 text-slate-600',
    };
    $caseLabel = fn (?string $category) => match ($category) {
        'return' => 'İade',
        'message' => 'Soru',
        'cargo' => 'Kargo',
        'supply' => 'Tedarik',
        'profit' => 'Finans',
        'crm_alert' => 'CRM Uyarı',
        default => 'Genel',
    };
    $actionTone = fn (?string $source) => match ($source) {
        'marketplace_orders' => 'border-sky-200 bg-sky-50 text-sky-700 hover:bg-sky-100',
        'marketplace_questions' => 'border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100',
        'returns', 'marketplace_claims' => 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100',
        'cargo_reports' => 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100',
        'supply_reports' => 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100',
        default => 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
    };
    $sortIcon = function (string $columnKey) use ($sortableColumns, $sortField, $sortDirection) {
        $dbColumn = $sortableColumns[$columnKey] ?? null;
        if (!$dbColumn) {
            return '';
        }

        return $sortField === $dbColumn
            ? ($sortDirection === 'asc' ? '▲' : '▼')
            : '⇅';
    };
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    @once
        <style>
            .crm-table {
                table-layout: fixed;
                width: 100%;
                min-width: 1040px;
            }

            .crm-table th,
            .crm-table td {
                overflow: hidden;
                text-overflow: ellipsis;
                vertical-align: top;
            }

            .crm-sortable {
                cursor: pointer;
                user-select: none;
            }

            .crm-sortable:hover {
                background: rgba(248, 250, 252, 0.95);
            }

            .crm-resize-handle {
                position: absolute;
                inset-block: 0;
                right: 0;
                width: 4px;
                cursor: col-resize;
                transition: background 0.15s;
            }

            .crm-resize-handle:hover,
            .crm-resize-handle.active {
                background: #0f172a;
            }
        </style>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('crmColumnResize', () => ({
                    resizing: false,
                    startX: 0,
                    startWidth: 0,
                    currentTh: null,
                    handle: null,
                    startResize(event, th) {
                        this.resizing = true;
                        this.startX = event.pageX;
                        this.startWidth = th.offsetWidth;
                        this.currentTh = th;
                        this.handle = event.target;
                        this.handle.classList.add('active');

                        const onMouseMove = (moveEvent) => {
                            if (!this.resizing) return;

                            const newWidth = Math.max(96, this.startWidth + (moveEvent.pageX - this.startX));
                            this.currentTh.style.width = newWidth + 'px';
                            this.currentTh.style.minWidth = newWidth + 'px';
                        };

                        const onMouseUp = () => {
                            this.resizing = false;
                            if (this.handle) this.handle.classList.remove('active');
                            document.removeEventListener('mousemove', onMouseMove);
                            document.removeEventListener('mouseup', onMouseUp);
                        };

                        document.addEventListener('mousemove', onMouseMove);
                        document.addEventListener('mouseup', onMouseUp);
                    },
                }));
            });
        </script>
    @endonce

    @if($workspaceMessage !== '')
        <div class="rounded-[8px] border p-4 text-sm {{ $workspaceMessageTone === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
            {{ $workspaceMessage }}
        </div>
    @endif

    @unless($crmReady)
        <section class="rounded-[10px] border border-amber-200 bg-amber-50 p-4 shadow-sm lg:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-amber-700">CRM Kurulum</p>
                    <h1 class="mt-2 text-xl font-semibold text-amber-950 lg:text-2xl">CRM tabloları hazır değil</h1>
                    <p class="mt-2 text-sm text-amber-800">Migration çalıştıktan sonra CRM projeksiyonu sipariş, soru, iade, kargo ve tedarik verisini müşteri merkezli hale getirecek.</p>
                </div>
                <div class="w-full rounded-[8px] border border-amber-200 bg-white px-4 py-3 text-sm text-amber-900 sm:w-auto">
                    <p class="font-mono">php artisan migrate</p>
                    <p class="mt-1 font-mono">php artisan crm:project</p>
                </div>
            </div>
        </section>
    @else
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(360px,0.9fr)]">
                <div class="min-w-0">
                    <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                        CRM Çalışma Alanı
                    </div>
                    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div class="min-w-0">
                            <h1 class="text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">Müşteri 360 Merkezi</h1>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                                Sipariş, soru, iade, kargo, tedarik ve finans sinyallerini tek müşteri defterinde izleyin.
                            </p>
                        </div>
                        <button type="button"
                                wire:click="refreshWorkspace"
                                wire:loading.attr="disabled"
                                wire:target="refreshWorkspace"
                                class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60 sm:w-auto sm:py-2">
                            <svg class="h-4 w-4 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0A8.003 8.003 0 018.064 13m11.355 2H15" />
                            </svg>
                            <span wire:loading.remove wire:target="refreshWorkspace">CRM’i Güncelle</span>
                            <span wire:loading wire:target="refreshWorkspace">İşleniyor...</span>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 xl:grid-cols-2">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Müşteri</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $formatCount($stats['contacts']) }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Açık Vaka</p>
                        <p class="mt-2 text-2xl font-semibold text-rose-600">{{ $formatCount($stats['open_cases']) }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Bugün SLA</p>
                        <p class="mt-2 text-2xl font-semibold text-amber-600">{{ $formatCount($stats['due_today']) }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Toplam Ciro</p>
                        <p class="mt-2 truncate text-xl font-semibold text-slate-950">{{ $formatMoney($stats['total_revenue']) }}</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_380px]">
            <section class="min-w-0 rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Müşteri Ledger</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-950">Canlı CRM kayıt defteri</h2>
                        @if(!empty($activeFilters))
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach($activeFilters as $filter)
                                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-600">{{ $filter }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        @include('livewire.partials.mp-column-toggle', ['columnDefs' => $columnDefs, 'visibleColumns' => $visibleColumns])
                        <button type="button"
                                wire:click="clearFilters"
                                class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                            Filtreleri Temizle
                        </button>
                    </div>
                </div>

                <div class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-6">
                        <div class="xl:col-span-2">
                            <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Arama</label>
                            <input type="search"
                                   wire:model.live.debounce.350ms="search"
                                   placeholder="Müşteri, telefon, sipariş, not..."
                                   class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                        </div>
                        <div>
                            <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Kaynak</label>
                            <select wire:model.live="sourceFilter"
                                    class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                                <option value="">Tümü</option>
                                @foreach($this->sourceOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Durum</label>
                            <select wire:model.live="statusFilter"
                                    class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                                <option value="">Tümü</option>
                                @foreach($this->statusOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Öncelik</label>
                            <select wire:model.live="priorityFilter"
                                    class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                                <option value="">Tümü</option>
                                @foreach($this->priorityOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Mağaza</label>
                            <select wire:model.live="storeFilter"
                                    class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                                <option value="">Tümü</option>
                                @foreach($stores as $store)
                                    <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <button type="button" wire:click="$set('sourceFilter', 'marketplace_questions')" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-left text-xs text-slate-600 transition hover:bg-slate-50">
                        <span class="block font-semibold text-slate-950">{{ $formatCount($stats['questions']) }}</span>
                        Yanıt kuyruğu
                    </button>
                    <button type="button" wire:click="$set('sourceFilter', 'returns')" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-left text-xs text-slate-600 transition hover:bg-slate-50">
                        <span class="block font-semibold text-slate-950">{{ $formatCount($stats['returns']) }}</span>
                        İade takibi
                    </button>
                    <button type="button" wire:click="$set('sourceFilter', 'cargo_reports')" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-left text-xs text-slate-600 transition hover:bg-slate-50">
                        <span class="block font-semibold text-slate-950">{{ $formatCount($stats['cargo']) }}</span>
                        Kargo riski
                    </button>
                    <button type="button" wire:click="$set('sourceFilter', 'supply_reports')" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-left text-xs text-slate-600 transition hover:bg-slate-50">
                        <span class="block font-semibold text-slate-950">{{ $formatCount($stats['supply']) }}</span>
                        Tedarik riski
                    </button>
                </div>

                <div class="mt-4 space-y-3 md:hidden">
                    @if($contacts->count() > 0)
                        @foreach($contacts as $contact)
                        <article wire:key="crm-mobile-{{ $contact->id }}"
                                 class="rounded-[8px] border border-slate-200 bg-white p-3 shadow-sm {{ $selectedContactId === $contact->id ? 'ring-2 ring-slate-900/10' : '' }}">
                            <button type="button" wire:click="selectContact({{ $contact->id }})" class="w-full text-left">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-950">{{ $contact->display_name }}</p>
                                        <p class="mt-1 truncate text-xs text-slate-500">{{ $contact->primary_phone ?: $contact->primary_email ?: ($contact->city ?: 'İletişim bilgisi yok') }}</p>
                                    </div>
                                    <span class="rounded-[6px] border px-2 py-0.5 text-[10px] font-medium {{ $contact->riskTone() === 'danger' ? 'border-rose-200 bg-rose-50 text-rose-700' : ($contact->riskTone() === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700') }}">
                                        Risk {{ $contact->risk_score }}
                                    </span>
                                </div>
                                <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                                    <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-1.5">
                                        <p class="text-slate-400">Sipariş</p>
                                        <p class="font-semibold text-slate-950">{{ $formatCount($contact->order_count) }}</p>
                                    </div>
                                    <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-1.5">
                                        <p class="text-slate-400">Vaka</p>
                                        <p class="font-semibold text-slate-950">{{ $formatCount($contact->open_case_count) }}</p>
                                    </div>
                                    <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-1.5">
                                        <p class="text-slate-400">Değer</p>
                                        <p class="font-semibold text-slate-950">{{ $contact->value_score }}</p>
                                    </div>
                                </div>
                                <p class="mt-3 line-clamp-2 text-xs leading-5 text-slate-500">{{ $contact->last_event_title ?: 'Henüz olay yok' }}</p>
                            </button>
                        </article>
                        @endforeach
                    @else
                        <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                            CRM kaydı bulunamadı. CRM’i güncelle butonu ile mevcut modüllerden veri projekte edebilirsiniz.
                        </div>
                    @endif
                </div>

                <div class="mt-4 hidden overflow-x-auto rounded-[8px] border border-slate-200 bg-white md:block" x-data="crmColumnResize()">
                    <table class="crm-table divide-y divide-slate-200">
                        <thead class="bg-slate-50/80">
                            <tr>
                                @foreach($columnDefs as $columnKey => $columnLabel)
                                    @if(in_array($columnKey, $visibleColumns, true))
                                        <th class="relative px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 {{ isset($sortableColumns[$columnKey]) ? 'crm-sortable' : '' }}"
                                            @if(isset($sortableColumns[$columnKey])) wire:click="sortTable('{{ $columnKey }}')" @endif>
                                            <span>{{ $columnLabel }}</span>
                                            @if(isset($sortableColumns[$columnKey]))
                                                <span class="ml-1 text-slate-300">{{ $sortIcon($columnKey) }}</span>
                                            @endif
                                            <span class="crm-resize-handle" @mousedown.prevent.stop="startResize($event, $el.closest('th'))"></span>
                                        </th>
                                    @endif
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @if($contacts->count() > 0)
                                @foreach($contacts as $contact)
                                <tr wire:key="crm-contact-{{ $contact->id }}" class="{{ $selectedContactId === $contact->id ? 'bg-slate-50/80' : 'hover:bg-slate-50/50' }}">
                                    @if(in_array('musteri', $visibleColumns, true))
                                        <td class="px-3 py-4">
                                            <button type="button" wire:click="selectContact({{ $contact->id }})" class="block max-w-full text-left">
                                                <span class="block truncate text-sm font-semibold text-slate-950">{{ $contact->display_name }}</span>
                                                <span class="mt-1 block truncate text-xs text-slate-500">{{ $contact->primary_phone ?: $contact->primary_email ?: ($contact->city ?: 'İletişim bilgisi yok') }}</span>
                                            </button>
                                        </td>
                                    @endif
                                    @if(in_array('son_olay', $visibleColumns, true))
                                        <td class="px-3 py-4">
                                            <span class="inline-flex rounded-[6px] border px-2 py-0.5 text-[10px] font-medium {{ $eventTone($contact->last_event_type) }}">
                                                {{ $eventLabel($contact->last_event_type) }}
                                            </span>
                                            <p class="mt-2 truncate text-xs font-medium text-slate-700">{{ $contact->last_event_title ?: 'Olay yok' }}</p>
                                            <p class="mt-1 text-[11px] text-slate-400">{{ $contact->last_event_at?->diffForHumans() ?: '-' }}</p>
                                        </td>
                                    @endif
                                    @if(in_array('siparis', $visibleColumns, true))
                                        <td class="px-3 py-4 text-sm text-slate-700">
                                            <p class="font-semibold text-slate-950">{{ $formatCount($contact->order_count) }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $contact->last_order_at?->format('d.m.Y') ?: 'Sipariş yok' }}</p>
                                        </td>
                                    @endif
                                    @if(in_array('ciro', $visibleColumns, true))
                                        <td class="px-3 py-4 text-sm">
                                            <p class="font-semibold text-slate-950">{{ $formatMoney($contact->gross_revenue_total) }}</p>
                                            <p class="mt-1 text-xs text-slate-500">Toplam alışveriş</p>
                                        </td>
                                    @endif
                                    @if(in_array('vaka', $visibleColumns, true))
                                        <td class="px-3 py-4 text-sm">
                                            <p class="font-semibold {{ $contact->open_case_count > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $formatCount($contact->open_case_count) }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $formatCount($contact->open_tasks_count ?? 0) }} açık görev</p>
                                        </td>
                                    @endif
                                    @if(in_array('risk', $visibleColumns, true))
                                        <td class="px-3 py-4">
                                            <div class="h-2 overflow-hidden rounded-[6px] bg-slate-100">
                                                <div class="h-full {{ $contact->risk_score >= 70 ? 'bg-rose-500' : ($contact->risk_score >= 40 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ min(100, $contact->risk_score) }}%"></div>
                                            </div>
                                            <p class="mt-2 text-xs font-semibold text-slate-700">{{ $contact->risk_score }}/100</p>
                                        </td>
                                    @endif
                                    @if(in_array('deger', $visibleColumns, true))
                                        <td class="px-3 py-4">
                                            <div class="h-2 overflow-hidden rounded-[6px] bg-slate-100">
                                                <div class="h-full bg-slate-900" style="width: {{ min(100, $contact->value_score) }}%"></div>
                                            </div>
                                            <p class="mt-2 text-xs font-semibold text-slate-700">{{ $contact->value_score }}/100</p>
                                        </td>
                                    @endif
                                    @if(in_array('aksiyon', $visibleColumns, true))
                                        <td class="px-3 py-4">
                                            <button type="button"
                                                    wire:click="selectContact({{ $contact->id }})"
                                                    class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                                360 Aç
                                            </button>
                                        </td>
                                    @endif
                                </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="{{ count($visibleColumns) }}" class="px-4 py-12 text-center text-sm text-slate-500">
                                        CRM kaydı bulunamadı. Filtreleri temizleyin veya CRM projeksiyonunu çalıştırın.
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $contacts->links() }}
                </div>
            </section>

            <aside class="min-w-0 rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
                @if($selectedContact)
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Müşteri 360</p>
                            <h2 class="mt-1 truncate text-lg font-semibold text-slate-950">{{ $selectedContact->display_name }}</h2>
                            <p class="mt-1 truncate text-sm text-slate-500">{{ $selectedContact->primary_phone ?: $selectedContact->primary_email ?: 'İletişim bilgisi yok' }}</p>
                        </div>
                        <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600">#{{ $selectedContact->id }}</span>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2">
                        <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-2">
                            <p class="text-[10px] text-slate-500">Sipariş</p>
                            <p class="mt-1 text-sm font-semibold text-slate-950">{{ $formatCount($selectedContact->order_count) }}</p>
                        </div>
                        <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-2">
                            <p class="text-[10px] text-slate-500">Risk</p>
                            <p class="mt-1 text-sm font-semibold text-slate-950">{{ $selectedContact->risk_score }}</p>
                        </div>
                        <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-2">
                            <p class="text-[10px] text-slate-500">Değer</p>
                            <p class="mt-1 text-sm font-semibold text-slate-950">{{ $selectedContact->value_score }}</p>
                        </div>
                    </div>

                    @if($selectedContact->relationLoaded('ledgerEntries'))
                        @php
                            $contactLedgerEntries = $selectedContact->ledgerEntries;
                            $contactLedgerGross = $contactLedgerEntries->sum('gross_amount');
                            $contactLedgerCommission = $contactLedgerEntries->sum('commission_amount');
                            $contactLedgerProfit = $contactLedgerEntries->sum('profit_amount');
                        @endphp
                        <div class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs font-semibold text-slate-900">Son Cari Özeti</p>
                                <a href="{{ route('crm.customer-ledger', ['contact' => $selectedContact->id]) }}"
                                   class="shrink-0 rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-600 transition hover:bg-slate-50">
                                    Cari Aç
                                </a>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2">
                                <div class="rounded-[6px] border border-slate-200 bg-white px-2 py-2">
                                    <p class="text-[10px] text-slate-500">Ciro</p>
                                    <p class="mt-1 truncate text-xs font-semibold text-slate-950">{{ $formatMoney($contactLedgerGross) }}</p>
                                </div>
                                <div class="rounded-[6px] border border-slate-200 bg-white px-2 py-2">
                                    <p class="text-[10px] text-slate-500">Kom.</p>
                                    <p class="mt-1 truncate text-xs font-semibold text-slate-950">{{ $formatMoney($contactLedgerCommission) }}</p>
                                </div>
                                <div class="rounded-[6px] border border-slate-200 bg-white px-2 py-2">
                                    <p class="text-[10px] text-slate-500">Kâr</p>
                                    <p class="mt-1 truncate text-xs font-semibold {{ $contactLedgerProfit < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $formatMoney($contactLedgerProfit) }}</p>
                                </div>
                            </div>
                            @if($contactLedgerEntries->isNotEmpty())
                                <div class="mt-3 space-y-1.5">
                                    @foreach($contactLedgerEntries->take(3) as $ledgerEntry)
                                        <div class="flex items-center justify-between gap-2 rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-xs">
                                            <span class="min-w-0 truncate text-slate-700">{{ $ledgerEntry->product_name }}</span>
                                            <span class="shrink-0 font-semibold {{ (float) $ledgerEntry->profit_amount < 0 ? 'text-rose-600' : 'text-slate-700' }}">{{ $formatMoney($ledgerEntry->profit_amount) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                        <p class="text-xs font-semibold text-slate-900">Kimlikler</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @if($selectedContact->identities->isNotEmpty())
                                @foreach($selectedContact->identities as $identity)
                                <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-[11px] text-slate-600">
                                    {{ $identity->store?->store_name ?: $sourceLabel($identity->source_type) }}
                                </span>
                                @endforeach
                            @else
                                <span class="text-xs text-slate-500">Kimlik bağlantısı yok.</span>
                            @endif
                        </div>
                    </div>

                    @php
                        $sourceActions = $selectedContact->timelineEvents
                            ->map(fn ($event) => $this->sourceActionForEvent($event))
                            ->filter()
                            ->unique('url')
                            ->take(5)
                            ->values();
                    @endphp
                    @if($sourceActions->isNotEmpty())
                        <div class="mt-4 rounded-[8px] border border-slate-200 bg-white p-3">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs font-semibold text-slate-900">Aksiyon Merkezi</p>
                                <span class="text-[11px] text-slate-500">{{ $sourceActions->count() }} hızlı geçiş</span>
                            </div>
                            <div class="mt-3 space-y-2">
                                @foreach($sourceActions as $action)
                                    <a href="{{ $action['url'] }}"
                                       class="flex min-h-[44px] items-center justify-between gap-3 rounded-[6px] border px-3 py-2 text-xs font-medium transition {{ $actionTone($action['source']) }}">
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm">{{ $action['label'] }}</span>
                                            <span class="mt-0.5 block truncate text-[11px] font-normal opacity-80">{{ $action['title'] }}</span>
                                        </span>
                                        <span class="shrink-0 text-base leading-none">›</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="mt-4 space-y-2">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold text-slate-900">Açık Vakalar</p>
                            <span class="text-xs text-slate-500">{{ $selectedContact->cases->whereIn('status', ['open', 'pending', 'in_progress'])->count() }} kayıt</span>
                        </div>
                        @if($selectedContact->cases->isNotEmpty())
                            @foreach($selectedContact->cases as $case)
                            @php($caseAction = $this->sourceActionForCase($case))
                            <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <span class="rounded-[6px] border px-2 py-0.5 text-[10px] font-medium {{ $caseTone($case->priority) }}">{{ $caseLabel($case->category) }}</span>
                                        <p class="mt-2 text-sm font-semibold text-slate-950">{{ $case->title }}</p>
                                        <p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">{{ $case->summary }}</p>
                                    </div>
                                    @if(!in_array($case->status, ['resolved', 'closed'], true))
                                        <button type="button" wire:click="resolveCase({{ $case->id }})" class="shrink-0 rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-600 transition hover:bg-slate-50">
                                            Kapat
                                        </button>
                                    @endif
                                </div>
                                <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-[11px] text-slate-400">{{ $case->sla_due_at ? 'SLA: ' . $case->sla_due_at->format('d.m.Y H:i') : 'SLA yok' }}</p>
                                    @if($caseAction)
                                        <a href="{{ $caseAction['url'] }}"
                                           class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] border px-3 py-2 text-[11px] font-medium transition sm:min-h-0 sm:py-1 {{ $actionTone($caseAction['source']) }}">
                                            {{ $caseAction['label'] }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        @else
                            <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-xs text-slate-500">
                                Bu müşteri için açık vaka yok.
                            </div>
                        @endif
                    </div>

                    <div class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                        <p class="text-xs font-semibold text-slate-900">Görev Ekle</p>
                        <div class="mt-3 space-y-2">
                            <input type="text" wire:model.defer="taskTitle" placeholder="Örn: müşteriyi ara"
                                   class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm sm:py-2 sm:text-sm">
                            <input type="datetime-local" wire:model.defer="taskDueAt"
                                   class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm sm:py-2 sm:text-sm">
                            <button type="button" wire:click="addTask" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 sm:py-2">
                                Görev Ekle
                            </button>
                        </div>
                        @if($selectedContact->openTasks->isNotEmpty())
                            <div class="mt-3 space-y-1.5">
                                @foreach($selectedContact->openTasks as $task)
                                    <div class="flex items-center justify-between gap-2 rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-xs">
                                        <span class="min-w-0 truncate text-slate-700">{{ $task->title }}</span>
                                        <button type="button" wire:click="completeTask({{ $task->id }})" class="shrink-0 text-slate-500 hover:text-slate-900">Tamamla</button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                        <p class="text-xs font-semibold text-slate-900">Not Ekle</p>
                        <textarea wire:model.defer="noteBody" rows="3" placeholder="Müşteriyle ilgili kısa not..."
                                  class="mt-3 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm sm:text-sm"></textarea>
                        <button type="button" wire:click="addNote" class="mt-2 inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:py-2">
                            Notu Kaydet
                        </button>
                    </div>

                    <div class="mt-4">
                        <p class="text-xs font-semibold text-slate-900">Timeline</p>
                        <div class="mt-3 space-y-2">
                            @if($selectedContact->timelineEvents->isNotEmpty())
                                @foreach($selectedContact->timelineEvents as $event)
                                @php($eventAction = $this->sourceActionForEvent($event))
                                <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <span class="rounded-[6px] border px-2 py-0.5 text-[10px] font-medium {{ $eventTone($event->event_type) }}">{{ $sourceLabel($event->source_type) }}</span>
                                            <p class="mt-2 text-sm font-semibold text-slate-950">{{ $event->title }}</p>
                                            @if($event->body)
                                                <p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">{{ $event->body }}</p>
                                            @endif
                                        </div>
                                        <span class="shrink-0 text-[11px] text-slate-400">{{ $event->occurred_at?->format('d.m') }}</span>
                                    </div>
                                    @if($eventAction)
                                        <a href="{{ $eventAction['url'] }}"
                                           class="mt-3 inline-flex min-h-[36px] w-full items-center justify-center rounded-[6px] border px-3 py-2 text-[11px] font-medium transition sm:min-h-0 sm:w-auto sm:py-1 {{ $actionTone($eventAction['source']) }}">
                                            {{ $eventAction['label'] }}
                                        </a>
                                    @endif
                                </div>
                                @endforeach
                            @else
                                <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-xs text-slate-500">
                                    Timeline kaydı yok.
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center">
                        <p class="text-sm font-semibold text-slate-900">Müşteri seçilmedi</p>
                        <p class="mt-1 text-xs text-slate-500">Ledger üzerinden bir müşteri seçerek 360 görünümünü açın.</p>
                    </div>
                @endif
            </aside>
        </div>
    @endunless
</div>
