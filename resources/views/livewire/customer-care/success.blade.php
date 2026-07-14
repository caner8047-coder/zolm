<div class="space-y-6 p-4 lg:p-6 bg-slate-50/50 min-h-screen">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-4 lg:p-6 rounded-[10px] border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-900">Müşteri Başarı Merkezi</h1>
            <p class="text-sm text-slate-500">Çok mağazalı portfolio sağlık durumu, risk görevleri ve operasyonel sinyaller.</p>
        </div>
        <div class="flex items-center gap-3">
            <select wire:model.live="selectedStoreId" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:outline-none">
                @foreach($stores as $st)
                    <option value="{{ $st->id }}">{{ $st->store_name }}</option>
                @endforeach
            </select>
            <button wire:click="refreshSnapshot" id="btn-refresh-snapshot"
                class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 text-white text-sm rounded-[6px] hover:bg-slate-700 transition">
                Yenile
            </button>
        </div>
    </div>

    @if($errorMessage)
        <div class="bg-red-50 border border-red-200 rounded-[8px] p-3 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif
    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-200 rounded-[8px] p-3 text-sm text-emerald-700">{{ $successMessage }}</div>
    @endif

    {{-- Snapshot --}}
    <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm p-4 lg:p-6">
        <h2 class="text-base font-semibold text-slate-900 mb-4">Sağlık Durumu</h2>
        @if($snapshot)
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                    <p class="text-xs text-slate-500 mb-1">Skor</p>
                    <p class="text-2xl font-bold text-slate-900">{{ $snapshot->health_score ?? '—' }}</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                    <p class="text-xs text-slate-500 mb-1">Durum</p>
                    <span class="inline-block px-2 py-0.5 text-xs font-mono rounded
                        {{ $snapshot->health_label === 'healthy' ? 'bg-emerald-100 text-emerald-800' :
                           ($snapshot->health_label === 'degraded' ? 'bg-amber-100 text-amber-800' :
                           ($snapshot->health_label === 'critical' ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-600')) }}">
                        {{ strtoupper($snapshot->health_label ?? 'unknown') }}
                    </span>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                    <p class="text-xs text-slate-500 mb-1">Hesaplanma</p>
                    <p class="text-xs text-slate-700">{{ $snapshot->computed_at?->diffForHumans() ?? '—' }}</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                    <p class="text-xs text-slate-500 mb-1">Bilinmeyenler</p>
                    <p class="text-xs text-slate-700">{{ implode(', ', $snapshot->unknown_components ?? []) ?: 'Yok' }}</p>
                </div>
            </div>
            @if($snapshot->is_stale)
                <div class="mt-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-[6px] px-3 py-2">
                    ⚠ Bu snapshot eski; güncel veri için yenileme yapınız.
                </div>
            @endif
        @else
            <div class="text-sm text-slate-400 py-8 text-center">Henüz snapshot yok. "Yenile" butonuna tıklayın.</div>
        @endif
    </div>

    {{-- Açık Görevler --}}
    <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm p-4 lg:p-6">
        <h2 class="text-base font-semibold text-slate-900 mb-4">Açık Görevler</h2>
        @if($tasks->isEmpty())
            <div class="text-sm text-slate-400 py-4 text-center">Açık görev yok.</div>
        @else
            <div class="divide-y divide-slate-100">
                @foreach($tasks as $task)
                    <div class="flex items-start justify-between py-3 gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800">{{ $task->task_type }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $task->description }}</p>
                        </div>
                        <button wire:click="resolveTask({{ $task->id }})" id="btn-resolve-task-{{ $task->id }}"
                            class="w-full sm:w-auto px-4 py-3 sm:py-2 shrink-0 text-xs bg-slate-900 text-white rounded-[6px] hover:bg-slate-700 transition">
                            Kapat
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Not Ekle --}}
    <div class="bg-white rounded-[10px] border border-slate-200 shadow-sm p-4 lg:p-6">
        <h2 class="text-base font-semibold text-slate-900 mb-3">Not Ekle <span class="text-xs text-slate-400 font-normal">(PII otomatik maskelenir)</span></h2>
        <div class="flex gap-3">
            <input wire:model="newNoteBody" type="text" id="input-note-body" placeholder="Not içeriği..."
                class="flex-1 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:outline-none" />
            <button wire:click="addNote" id="btn-add-note"
                class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-slate-900 text-white text-sm rounded-[6px] hover:bg-slate-700 transition">
                Ekle
            </button>
        </div>
    </div>
</div>
