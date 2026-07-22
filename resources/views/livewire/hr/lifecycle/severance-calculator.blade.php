<div class="space-y-6">
    <!-- Header Card -->
    <div class="rounded-[10px] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 border border-indigo-200">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    4857 SAYILI İŞ KANUNU HESAPLAYICISI
                </span>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">Kıdem ve İhbar Tazminatı Motoru</h1>
                <p class="mt-1 text-sm text-slate-500">Çalışan kıdem süresi, tavan kontrolü, giydirilmiş brüt ücret ve yasal vergi kesintileri ile anlık tazminat simülasyonu.</p>
            </div>
            <div>
                <a href="{{ route('hr.lifecycle') }}" class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                    ← Yaşam Döngüsüne Dön
                </a>
            </div>
        </div>
    </div>

    <!-- Calculator Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Form Inputs (Left Column) -->
        <div class="lg:col-span-5 rounded-[10px] border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <h3 class="text-base font-semibold text-slate-900 border-b border-slate-200 pb-3">Hesaplama Parametreleri</h3>

            <div>
                <label class="block text-xs font-medium text-slate-700">Mevcut Çalışandan Seç (Opsiyonel)</label>
                <select wire:model.live="selectedEmployeeId" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm bg-white focus:border-indigo-500 focus:outline-none">
                    <option value="">-- Manuel Hesaplama --</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->first_name }} {{ $emp->last_name }} ({{ $emp->employee_number }})</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-700">İşe Başlama Tarihi</label>
                    <input type="date" wire:model.live="startDate" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700">İşten Ayrılış Tarihi</label>
                    <input type="date" wire:model.live="endDate" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700">Aylık Brüt Maaş (₺)</label>
                <input type="number" step="0.01" wire:model.live="monthlyGrossSalary" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700">Aylık Düzenli Yan Haklar / Giydirme (₺)</label>
                <p class="text-[11px] text-slate-400">Yemek kartı, yol yardımı, düzenli prim ve yakacak desteği vb.</p>
                <input type="number" step="0.01" wire:model.live="monthlyBenefits" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700">Kıdem Tazminatı Tavanı (₺)</label>
                <input type="number" step="0.01" wire:model.live="severanceCeiling" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono bg-slate-50 focus:border-indigo-500 focus:outline-none">
            </div>

            <button wire:click="calculate" class="w-full rounded-md bg-slate-900 px-4 py-2.5 text-sm font-medium text-white shadow hover:bg-slate-800 focus:outline-none">
                Yeniden Hesapla
            </button>
        </div>

        <!-- Result Output (Right Column) -->
        <div class="lg:col-span-7 space-y-4">
            @if($result)
                <!-- Total Net Box -->
                <div class="rounded-[10px] border border-emerald-200 bg-emerald-50/70 p-6 shadow-sm">
                    <div class="text-xs font-bold uppercase tracking-wider text-emerald-800">Ödenecek Toplam Net Tazminat</div>
                    <div class="mt-2 text-3xl font-extrabold font-mono text-emerald-900">
                        ₺{{ number_format($result['summary']['total_net_payable'], 2, ',', '.') }}
                    </div>
                    <div class="mt-2 text-xs text-emerald-700 flex items-center justify-between border-t border-emerald-200/60 pt-2">
                        <span>Çalışma Süresi: <strong>{{ $result['tenure']['tenure_human'] }}</strong> ({{ $result['tenure']['total_days'] }} Gün)</span>
                        <span>Giydirilmiş Brüt: <strong>₺{{ number_format($result['base']['adjusted_gross'], 2, ',', '.') }}</strong></span>
                    </div>
                </div>

                @if($result['base']['ceiling_applied'])
                    <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <span>Giydirilmiş brüt ücret tavanı aştığı için kıdem tazminatı yasal tavan olan <strong>₺{{ number_format($result['base']['severance_ceiling'], 2, ',', '.') }}</strong> üzerinden hesaplanmıştır.</span>
                    </div>
                @endif

                <!-- Severance Card -->
                <div class="rounded-[10px] border border-slate-200 bg-white p-5 shadow-sm space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <h4 class="font-semibold text-slate-900 text-sm">1. Kıdem Tazminatı (4857 SK m.14)</h4>
                        <span class="text-xs font-mono font-bold text-slate-700">Net: ₺{{ number_format($result['severance']['net_amount'], 2, ',', '.') }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-xs">
                        <div class="bg-slate-50 p-2.5 rounded border border-slate-100">
                            <span class="text-slate-500 block">Brüt Kıdem</span>
                            <span class="font-mono font-semibold text-slate-800">₺{{ number_format($result['severance']['gross_amount'], 2, ',', '.') }}</span>
                        </div>
                        <div class="bg-slate-50 p-2.5 rounded border border-slate-100">
                            <span class="text-slate-500 block">Gelir Vergisi</span>
                            <span class="font-mono font-semibold text-emerald-600">Muaf (₺0,00)</span>
                        </div>
                        <div class="bg-slate-50 p-2.5 rounded border border-slate-100">
                            <span class="text-slate-500 block">Damga Vergisi (‰7.59)</span>
                            <span class="font-mono font-semibold text-rose-600">-₺{{ number_format($result['severance']['stamp_tax'], 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Notice Card -->
                <div class="rounded-[10px] border border-slate-200 bg-white p-5 shadow-sm space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <h4 class="font-semibold text-slate-900 text-sm">2. İhbar Tazminatı (4857 SK m.17 - {{ $result['notice']['notice_weeks'] }} Hafta / {{ $result['notice']['notice_days'] }} Gün)</h4>
                        <span class="text-xs font-mono font-bold text-slate-700">Net: ₺{{ number_format($result['notice']['net_amount'], 2, ',', '.') }}</span>
                    </div>
                    <div class="grid grid-cols-4 gap-2 text-xs">
                        <div class="bg-slate-50 p-2.5 rounded border border-slate-100">
                            <span class="text-slate-500 block">Brüt İhbar</span>
                            <span class="font-mono font-semibold text-slate-800">₺{{ number_format($result['notice']['gross_amount'], 2, ',', '.') }}</span>
                        </div>
                        <div class="bg-slate-50 p-2.5 rounded border border-slate-100">
                            <span class="text-slate-500 block">Gelir Vergisi (%15)</span>
                            <span class="font-mono font-semibold text-rose-600">-₺{{ number_format($result['notice']['income_tax'], 2, ',', '.') }}</span>
                        </div>
                        <div class="bg-slate-50 p-2.5 rounded border border-slate-100">
                            <span class="text-slate-500 block">Damga Vergisi</span>
                            <span class="font-mono font-semibold text-rose-600">-₺{{ number_format($result['notice']['stamp_tax'], 2, ',', '.') }}</span>
                        </div>
                        <div class="bg-slate-50 p-2.5 rounded border border-slate-100">
                            <span class="text-slate-500 block">Net İhbar</span>
                            <span class="font-mono font-semibold text-slate-900">₺{{ number_format($result['notice']['net_amount'], 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
