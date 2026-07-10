@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    {{-- Mesaj Paneli --}}
    @if($message !== '')
        <div class="rounded-[8px] border p-4 text-sm {{ $messageType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
            {{ $message }}
        </div>
    @endif

    {{-- Üst Section --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    Mutabakat Denetimi
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">Hata Denetim Motoru Logları</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Pazaryeri komisyon uyuşmazlıkları, kargo barem aşımı cezaları ve stopaj doğrulamalarında denetim bulgularını inceleyin ve çözüme kavuşturun.
                </p>
            </div>
        </div>
    </section>

    {{-- Denetim Tetikleme Paneli --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col sm:flex-row sm:items-end gap-3">
            <div class="flex-1">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Denetim Çalıştırılacak Dönem</label>
                <select wire:model="activePeriodId" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                    <option value="">Seçiniz...</option>
                    @foreach($this->periods as $p)
                        <option value="{{ $p->id }}">{{ $p->year }} / {{ sprintf('%02d', $p->month) }} - {{ strtoupper($p->marketplace) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button wire:click="runAudit" class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-2 text-sm font-semibold text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px]">
                    Yeni Denetim Çalıştır
                </button>
            </div>
        </div>
    </section>

    {{-- Filtreler & Tablo Section --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
        {{-- Filtre Satırı --}}
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div class="sm:col-span-1">
                <input type="text" wire:model.live="search" placeholder="Bulgu başlığı veya kodu ile ara..." class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
            </div>
            <div>
                <select wire:model.live="filterStatus" class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                    <option value="">Tüm Bulgular</option>
                    <option value="open">Açık Bulgular</option>
                    <option value="resolved">Çözülenler</option>
                    <option value="ignored">Göz Ardı Edilenler</option>
                </select>
            </div>
            <div>
                <select wire:model.live="filterSeverity" class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                    <option value="">Tüm Önem Dereceleri</option>
                    <option value="critical">Kritik (Para Kaybı)</option>
                    <option value="warning">Uyarı (Operasyonel Risk)</option>
                    <option value="info">Bilgi</option>
                </select>
            </div>
            <div>
                <select wire:model.live="filterPeriod" class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                    <option value="">Tüm Dönemler</option>
                    @foreach($this->periods as $p)
                        <option value="{{ $p->id }}">{{ $p->year }} / {{ sprintf('%02d', $p->month) }} - {{ strtoupper($p->marketplace) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Tablo --}}
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-sm text-slate-600 min-w-[900px]">
                <thead>
                    <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50/50">
                        <th class="p-4 w-32">Kural Kodu</th>
                        <th class="p-4">Bulgu Başlığı / Detay</th>
                        <th class="p-4 w-36">İlişkili Kayıt</th>
                        <th class="p-4 text-right w-24">Sapma</th>
                        <th class="p-4 text-center w-28">Derece</th>
                        <th class="p-4 text-center w-28">Durum</th>
                        <th class="p-4 text-center w-40">Aksiyon</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($this->logs as $log)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="p-4 font-mono font-bold text-slate-900 text-xs uppercase">{{ $log->rule_code }}</td>
                            <td class="p-4">
                                <div class="font-semibold text-slate-900">{{ $log->title }}</div>
                                <div class="text-xs text-slate-500 mt-1">{{ $log->description }}</div>
                                @if($log->resolution_note)
                                    <div class="mt-2 text-[11px] bg-slate-50 text-slate-600 p-2 rounded border border-slate-200">
                                        <span class="font-semibold text-slate-700">Çözüm Notu:</span> {{ $log->resolution_note }}
                                    </div>
                                @endif
                            </td>
                            <td class="p-4 text-slate-700 text-xs">
                                <div>Dönem: {{ $log->period->year }}/{{ sprintf('%02d', $log->period->month) }}</div>
                                @if($log->order)
                                    <div class="mt-1 font-mono text-[10px] text-slate-500">Sipariş: #{{ $log->order->order_number }}</div>
                                @endif
                            </td>
                            <td class="p-4 text-right font-mono font-bold text-slate-900">
                                {{ $log->difference > 0 ? $formatMoney($log->difference) : '-' }}
                            </td>
                            <td class="p-4 text-center">
                                @if($log->severity === 'critical')
                                    <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700 ring-1 ring-inset ring-rose-600/10">Kritik</span>
                                @elseif($log->severity === 'warning')
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/10">Uyarı</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-600/10">Bilgi</span>
                                @endif
                            </td>
                            <td class="p-4 text-center">
                                @if($log->status === 'open')
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700 ring-1 ring-inset ring-slate-600/10">Açık</span>
                                @elseif($log->status === 'resolved')
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/10">Çözüldü</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gray-50 px-2 py-0.5 text-xs font-semibold text-gray-600 ring-1 ring-inset ring-gray-600/10">Göz Ardı</span>
                                @endif
                            </td>
                            <td class="p-4 text-center">
                                @if($log->status === 'open')
                                    <div class="flex flex-col gap-1 sm:flex-row justify-center">
                                        <button type="button" wire:click="selectLog({{ $log->id }}, 'resolve')" class="px-2.5 py-1.5 text-xs font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[36px]">
                                            Çöz
                                        </button>
                                        <button type="button" wire:click="selectLog({{ $log->id }}, 'ignore')" class="px-2.5 py-1.5 text-xs font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 rounded-[6px] transition-colors min-h-[36px]">
                                            Göz Ardı
                                        </button>
                                    </div>
                                @else
                                    <button type="button" wire:click="reopenLog({{ $log->id }})" class="px-3 py-1.5 text-xs font-medium text-slate-600 bg-slate-50 border border-slate-200 hover:bg-slate-100 rounded-[6px] transition-colors min-h-[36px]">
                                        Tekrar Aç
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400">
                                Denetim bulgusu bulunamadı.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->logs->links() }}
        </div>
    </section>

    {{-- Çözüm / Göz Ardı Modal --}}
    @if($selectedLogId !== null)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white rounded-[10px] border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
                <div class="p-6 space-y-4">
                    <h3 class="text-base font-bold text-slate-900">
                        {{ $actionType === 'resolve' ? 'Bulguyu Çözüldü İşaretle' : 'Bulguyu Göz Ardı Et' }}
                    </h3>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Çözüm / Karar Notu</label>
                        <textarea wire:model="resolutionNote" rows="4" placeholder="Notunuzu buraya yazın..." class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none"></textarea>
                    </div>

                    <div class="flex gap-2 justify-end pt-2 border-t border-slate-100">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 border border-slate-200 rounded-[6px] hover:bg-slate-200 transition-colors min-h-[44px]">
                            Vazgeç
                        </button>
                        <button type="button" wire:click="saveResolution" class="px-4 py-2 text-sm font-semibold text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px]">
                            Kaydet
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
