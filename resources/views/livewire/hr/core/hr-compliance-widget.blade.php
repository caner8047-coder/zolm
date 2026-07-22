<div class="rounded-[10px] border border-slate-200 bg-white p-5 shadow-sm space-y-4">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div class="flex items-center gap-2">
            <div class="rounded-md bg-amber-50 p-1.5 text-amber-700 border border-amber-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Yasal Uyum & Risk Kokpiti (4857 / 5510 SK)</h3>
                <p class="text-xs text-slate-500">Mevzuat limitleri, fazla mesai sınırı, gece vardiyaları ve SGK süre takipleri</p>
            </div>
        </div>
        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-mono font-medium text-slate-600">Canlı Denetim</span>
    </div>

    @php
        $overtimes = $auditResults['overtime_warnings'] ?? [];
        $nightShifts = $auditResults['night_shift_warnings'] ?? [];
        $accidents = $auditResults['safety_incident_warnings'] ?? [];
        $quotas = $auditResults['quota_warnings'] ?? [];
        $totalWarningsCount = count($overtimes) + count($nightShifts) + count($accidents) + count($quotas);
    @endphp

    @if($totalWarningsCount === 0)
        <div class="rounded-md bg-emerald-50 border border-emerald-200 p-4 text-center">
            <svg class="w-6 h-6 text-emerald-600 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <p class="text-xs font-medium text-emerald-900">Tüm yasal mevzuat limitleri ve SGK bildirim süreleri uyumlu durumda.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <!-- Overtime Warnings -->
            @foreach($overtimes as $item)
                <div class="rounded-md border p-3 text-xs {{ $item['severity'] === 'critical' ? 'border-red-200 bg-red-50/70 text-red-900' : 'border-amber-200 bg-amber-50/70 text-amber-900' }}">
                    <div class="flex items-center justify-between font-semibold">
                        <span>{{ $item['employee_name'] }}</span>
                        <span class="font-mono text-[11px]">{{ $item['total_hours'] }} / 270 Saat</span>
                    </div>
                    <p class="mt-1 text-[11px] opacity-90">{{ $item['message'] }}</p>
                </div>
            @endforeach

            <!-- Night Shift Warnings -->
            @foreach($nightShifts as $item)
                <div class="rounded-md border border-amber-200 bg-amber-50/70 p-3 text-xs text-amber-900">
                    <div class="flex items-center justify-between font-semibold">
                        <span>{{ $item['employee_name'] }} (Gece Vardiyası)</span>
                        <span class="font-mono text-[11px]">{{ $item['duration_hours'] }} Saat</span>
                    </div>
                    <p class="mt-1 text-[11px] opacity-90">{{ $item['message'] }}</p>
                </div>
            @endforeach

            <!-- Safety Accident Warnings -->
            @foreach($accidents as $item)
                <div class="rounded-md border border-red-200 bg-red-50/70 p-3 text-xs text-red-900">
                    <div class="flex items-center justify-between font-semibold">
                        <span>İş Kazası Bildirimi: {{ $item['incident_number'] }}</span>
                        <span class="font-mono text-[11px]">{{ $item['days_passed'] }} İş Günü</span>
                    </div>
                    <p class="mt-1 text-[11px] opacity-90">{{ $item['message'] }}</p>
                </div>
            @endforeach

            <!-- Quota Warnings -->
            @foreach($quotas as $item)
                <div class="rounded-md border border-blue-200 bg-blue-50/70 p-3 text-xs text-blue-900">
                    <div class="flex items-center justify-between font-semibold">
                        <span>Engelli Personel İstihdam Kotası (%3)</span>
                        <span class="font-mono text-[11px]">{{ $item['required_quota'] }} Kişi Kota</span>
                    </div>
                    <p class="mt-1 text-[11px] opacity-90">{{ $item['message'] }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
