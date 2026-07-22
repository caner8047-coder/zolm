<div class="space-y-4 lg:space-y-6">
<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
<div>
<h1 class="text-xl lg:text-2xl font-semibold text-slate-900">Bordro Çalışma Alanı</h1>
<p class="mt-1 text-sm text-slate-500">Kapanmış puantajı dondurun, onaylı kural ve ücret sürümleriyle açıklanabilir brüt–net hesabı üretin.</p>
</div><div class="flex flex-col sm:flex-row gap-2">@if(auth()->user()?->hasHrPermission('hr.payroll.calculate')&&auth()->user()?->hasHrPermission('hr.salary.view'))<a href="{{ route('hr.payroll.calculator') }}" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] border border-slate-200 bg-white text-center text-sm">Brüt–net aracı</a>@endif @if(auth()->user()?->hasHrPermission('hr.payroll.manage_profiles'))<a href="{{ route('hr.settings.payroll-profiles') }}" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] border border-slate-200 bg-white text-center text-sm">Çalışan profilleri</a>@endif @if(auth()->user()?->hasHrPermission('hr.payroll.manage_rules'))<a href="{{ route('hr.settings.payroll-rules') }}" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] border border-slate-200 bg-white text-center text-sm">Kural sürümleri</a>@endif</div></div>@if(session('success'))<div class="rounded-[8px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
@if(auth()->user()?->hasHrPermission('hr.payroll.calculate'))<form wire:submit="prepare" class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
<select wire:model.defer="timesheetPeriodId" class="text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2">
<option value="">Kapanmış puantaj dönemi seçin</option>@foreach($closedTimesheets as $item)<option value="{{ $item->id }}">{{ $item->name }} · {{ $item->starts_on->format('d.m.Y') }}–{{ $item->ends_on->format('d.m.Y') }}</option>@endforeach</select>
<button class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] bg-slate-900 text-sm text-white">Hazırlık paketi oluştur</button>
</div>@error('timesheetPeriodId')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror</form>@endif
<div class="grid grid-cols-1 xl:grid-cols-4 gap-3 lg:gap-4">
<aside class="rounded-[10px] border border-slate-200 bg-white shadow-sm overflow-hidden">
<div class="border-b border-slate-200 bg-slate-50/60 p-4 text-sm font-semibold text-slate-900">Paketler</div>
<div class="divide-y divide-slate-100">@forelse($periods as $item)<button wire:click="select({{ $item->id }})" class="w-full p-4 text-left {{ $selectedPeriodId===$item->id?'bg-slate-50':'' }}">
<span class="block text-sm font-medium text-slate-900">{{ $item->name }}</span>
<span class="mt-1 block text-xs text-slate-500">{{ $item->records_count }} çalışan · {{ $item->status }}</span>
</button>@empty<div class="p-6 text-sm text-slate-500">Paket yok.</div>@endforelse</div>
</aside>
<section class="xl:col-span-3 rounded-[10px] border border-slate-200 bg-white shadow-sm overflow-hidden">@if($selected)<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-200 bg-slate-50/60 p-4">
<div class="min-w-0">
<h2 class="text-sm font-semibold text-slate-900">{{ $selected->name }}</h2>
<p class="mt-1 truncate text-xs text-slate-500">Kaynak: {{ $selected->timesheetPeriod?->name }} · {{ $selected->source_status }} · {{ substr($selected->source_hash,0,12) }}… · Hesap: {{ $selected->preflight_status }} · Çıktı: {{ $selected->output_preflight_status }}</p>
</div>
<div class="flex flex-col sm:flex-row flex-wrap gap-2">@if($selected->source_status==='stale' && $selected->status==='approved')<span class="px-3 py-2 text-xs font-mono rounded-[6px] bg-amber-50 text-amber-800">Ek/fark bordrosu gerekli</span>@elseif($selected->source_status==='stale' && auth()->user()?->hasHrPermission('hr.payroll.calculate'))<button wire:click="refreshSource" wire:confirm="Paket güncel puantaj, fazla mesai, kural ve bordro profilleriyle yeniden hazırlansın mı?" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] bg-slate-900 text-sm text-white">Kaynağı yenile</button>@elseif($selected->status==='prepared' && auth()->user()?->hasHrPermission('hr.payroll.calculate'))<button wire:click="calculate" wire:confirm="Onaylı ücret ve kural sürümleriyle bordro hesaplansın mı?" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] bg-slate-900 text-sm text-white">Brüt–net hesapla</button>@elseif($selected->status==='calculated' && auth()->user()?->hasHrPermission('hr.payroll.approve'))<button wire:click="approve" wire:confirm="Hesaplanan bordro onaylanıp dondurulsun mu?" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] bg-slate-900 text-sm text-white">Onayla ve dondur</button>@elseif($selected->status==='approved' || $selected->status==='calculated')<a href="{{ route('hr.payroll.control-output',$selected) }}" class="w-full sm:w-auto px-3 py-2 rounded-[6px] border border-slate-200 bg-white text-center text-xs text-slate-700 hover:bg-slate-50">Kontrol Excel'i</a><a href="{{ route('hr.payroll.mphbt-export',$selected) }}" class="w-full sm:w-auto px-3 py-2 rounded-[6px] border border-slate-200 bg-white text-center text-xs text-slate-700 hover:bg-slate-50">📥 SGK MPHBT TXT</a><a href="{{ route('hr.payroll.bank-payment-export',$selected) }}" class="w-full sm:w-auto px-3 py-2 rounded-[6px] border border-slate-200 bg-white text-center text-xs text-slate-700 hover:bg-slate-50">🏦 Banka Ödeme CSV</a><a href="{{ route('hr.payroll.journal-voucher',$selected) }}" target="_blank" class="w-full sm:w-auto px-3 py-2 rounded-[6px] border border-slate-200 bg-white text-center text-xs text-slate-700 hover:bg-slate-50">🧾 Yevmiye Fişi (JSON)</a><a href="{{ route('hr.payroll.operations-package',$selected) }}" class="w-full sm:w-auto px-4 py-2 rounded-[6px] bg-slate-900 text-center text-xs text-white">İcmal ve Pusulalar</a>@else<span class="px-2 py-0.5 text-xs font-mono rounded bg-emerald-50 text-emerald-700">{{ $selected->status }}</span>@endif</div>
</div>@if($selected->source_status==='stale')<div class="border-b border-amber-200 bg-amber-50 p-4"><p class="text-sm font-medium text-amber-900">Bordro kaynağı güncelliğini yitirdi.</p>@foreach($selected->source_stale_findings??[] as $finding)<p class="mt-1 text-xs text-amber-800">{{ $finding['message'] }}</p>@endforeach</div>@endif @if($selected->preflight_status==='failed'||$selected->output_preflight_status==='failed')<div class="border-b border-red-200 bg-red-50 p-4">
<p class="text-sm font-medium text-red-800">Ön kontroller tamamlanamadı.</p>@foreach(array_merge($selected->preflight_findings??[],$selected->output_preflight_findings??[]) as $finding)<p class="mt-1 text-xs text-red-700">{{ $finding['message'] }}</p>@endforeach</div>@endif<div class="hidden md:block overflow-x-auto rounded-lg">
<table class="w-full table-fixed">
<thead class="bg-slate-50/60 text-left text-xs uppercase text-slate-500">
<tr>
<th class="px-4 py-3">Çalışan</th>
<th class="px-4 py-3">Çalışma</th>
<th class="px-4 py-3">İzin</th>
<th class="px-4 py-3">Onaylı fazla</th>
<th class="px-4 py-3">Eksik</th>@if(auth()->user()?->hasHrPermission('hr.salary.view'))<th class="px-4 py-3">Brüt</th>
<th class="px-4 py-3">Net</th>@endif</tr>
</thead>
<tbody class="divide-y divide-slate-100">@foreach($selected->records as $record)<tr>
<td class="overflow-hidden text-ellipsis px-4 py-3 text-sm font-medium text-slate-900">{{ $record->employee?->full_name }}</td>@foreach(['worked_minutes','leave_minutes','approved_overtime_minutes','missing_minutes'] as $field)<td class="px-4 py-3 text-sm text-slate-600">{{ intdiv($record->$field,60) }}s {{ $record->$field%60 }}dk</td>@endforeach @if(auth()->user()?->hasHrPermission('hr.salary.view'))<td class="px-4 py-3 text-sm text-slate-700">{{ $record->gross_pay_encrypted?number_format($record->grossPay(),2,',','.').' ₺':'—' }}</td>
<td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $record->net_pay_encrypted?number_format($record->netPay(),2,',','.').' ₺':'—' }}</td>@endif</tr>@endforeach</tbody>
</table>
</div>
<div class="md:hidden divide-y divide-slate-100">@foreach($selected->records as $record)<article class="p-4">
<p class="text-sm font-medium text-slate-900">{{ $record->employee?->full_name }}</p>
<p class="mt-1 text-xs text-slate-500">Çalışma {{ $record->worked_minutes }}dk · Onaylı fazla {{ $record->approved_overtime_minutes }}dk · Eksik {{ $record->missing_minutes }}dk</p>@if(auth()->user()?->hasHrPermission('hr.salary.view')&&$record->net_pay_encrypted)<p class="mt-2 text-sm font-medium text-slate-900">Net {{ number_format($record->netPay(),2,',','.') }} ₺</p>@endif</article>@endforeach</div>@else<div class="p-10 text-center text-sm text-slate-500">Bir bordro hazırlık paketi seçin.</div>@endif</section>
</div>
@if($selected && in_array($selected->variance_status,['warning','critical'],true))
<section class="rounded-[10px] border {{ $selected->variance_status==='critical'?'border-red-200':'border-amber-200' }} bg-white shadow-sm">
<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 border-b border-slate-200 p-4">
<div><h3 class="text-sm font-semibold text-slate-900">Önceki dönem fark kontrolü</h3><p class="mt-1 text-xs text-slate-500">{{ count($selected->variance_findings??[]) }} değişiklik bulundu · {{ $selected->variance_status }}</p></div>
@if($selected->variance_reviewed_at)<span class="px-2 py-0.5 text-xs font-mono rounded bg-emerald-50 text-emerald-700">İncelendi</span>@endif
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 p-4">
@foreach($selected->variance_findings??[] as $finding)<article class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><div class="flex items-center justify-between gap-2"><span class="truncate text-xs font-medium text-slate-900">{{ $selected->records->firstWhere('employee_id',$finding['employee_id']??null)?->employee?->full_name??'Dönem geneli' }}</span><span class="px-2 py-0.5 text-xs font-mono rounded {{ ($finding['severity']??'info')==='critical'?'bg-red-100 text-red-700':'bg-amber-100 text-amber-700' }}">{{ $finding['severity']??'info' }}</span></div><p class="mt-2 text-xs text-slate-600">{{ $finding['message'] }}</p>@if(isset($finding['details']['change_percent']))<p class="mt-1 text-xs font-medium text-slate-900">Değişim: %{{ number_format($finding['details']['change_percent'],2,',','.') }}</p>@endif</article>@endforeach
</div>
@if(!$selected->variance_reviewed_at && auth()->user()?->hasHrPermission('hr.payroll.approve') && $selected->calculated_by!==auth()->id())<form wire:submit="reviewVariance" class="flex flex-col sm:flex-row gap-3 border-t border-slate-200 p-4"><input wire:model.defer="varianceReviewNote" class="min-w-0 flex-1 text-base sm:text-sm rounded-[6px] border border-slate-200 px-3 py-3 sm:py-2" placeholder="Farkların nedenini ve kontrol sonucunu yazın"><button class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] bg-slate-900 text-sm text-white">İncelendi olarak kaydet</button>@error('varianceReviewNote')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</form>@endif
</section>
@endif
@if($selected?->status==='prepared'&&auth()->user()?->hasHrPermission('hr.payroll.calculate'))
<section x-data="{open:false}" class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
<button type="button" @click="open=!open" class="flex w-full items-center justify-between gap-3 p-4 text-left"><span><strong class="block text-sm text-slate-900">Kazanç, kesinti, istisna ve teşvikler</strong><small class="mt-1 block text-xs text-slate-500">Hesap öncesinde ek kalemleri çift onayla yönetin.</small></span><span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-100">{{ $adjustments->count() }} kalem</span></button>
<div x-show="open" x-cloak class="border-t border-slate-200 p-4 lg:p-6">
<form wire:submit="proposeAdjustment" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
<select wire:model.defer="adjustmentEmployeeId" class="text-base sm:text-sm rounded-[6px] border border-slate-200 px-3 py-3 sm:py-2"><option value="">Çalışan seçin</option>@foreach($selected->records as $record)<option value="{{ $record->employee_id }}">{{ $record->employee?->full_name }}</option>@endforeach</select>
<select wire:model.live="adjustmentCode" class="text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2"><option value="">Standart kalem seçin</option>@foreach($payrollItems as $code => $item)<option value="{{ $code }}">{{ $code }} · {{ $item['name'] }}</option>@endforeach</select>
<div class="rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm"><span class="block font-medium text-slate-900">{{ $adjustmentName ?: 'Kalem sınıflandırması' }}</span><span class="text-xs text-slate-500">{{ $adjustmentType }}</span></div>
<input type="number" step="0.01" min="0.01" wire:model.defer="adjustmentAmount" class="text-base sm:text-sm rounded-[6px] border border-slate-200 px-3 py-3 sm:py-2" placeholder="Tutar">
<input wire:model.defer="adjustmentReason" class="text-base sm:text-sm rounded-[6px] border border-slate-200 px-3 py-3 sm:py-2" placeholder="Gerekçe">
<div class="sm:col-span-2 xl:col-span-3 flex flex-col sm:flex-row gap-3 text-xs text-slate-600"><span class="rounded bg-slate-100 px-2 py-1">SGK: {{ $socialSecurityExempt ? 'istisnalı' : 'matraha dahil' }}</span><span class="rounded bg-slate-100 px-2 py-1">Gelir vergisi: {{ $incomeTaxExempt ? 'istisnalı' : 'matraha dahil' }}</span>@if($preTaxDeduction)<span class="rounded bg-amber-50 px-2 py-1 text-amber-700">Vergi öncesi kesinti</span>@endif<span>Vergi sınıfı katalogdan gelir, kullanıcı tarafından değiştirilemez.</span></div>
@if($errors->any())<p class="sm:col-span-2 xl:col-span-3 text-xs text-red-600">{{ $errors->first() }}</p>@endif
<button class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] bg-slate-900 text-sm text-white">Onaya gönder</button>
</form>
<div class="mt-4 divide-y divide-slate-100 rounded-lg border border-slate-200">@forelse($adjustments as $adjustment)<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 p-3"><div class="min-w-0"><p class="truncate text-sm font-medium text-slate-900">{{ $adjustment->employee?->full_name }} · {{ $adjustment->name }}</p><p class="text-xs text-slate-500">{{ $adjustment->code }} · {{ $adjustment->type }} · {{ number_format($adjustment->amountCents()/100,2,',','.') }} ₺ · {{ $adjustment->status }}</p></div>@if($adjustment->status==='pending_approval'&&$adjustment->created_by!==auth()->id()&&auth()->user()?->hasHrPermission('hr.payroll.approve'))<button wire:click="approveAdjustment({{ $adjustment->id }})" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] border border-slate-200 bg-white text-xs">Onayla</button>@endif</div>@empty<p class="p-3 text-xs text-slate-500">Ek bordro kalemi yok.</p>@endforelse</div>
</div>
</section>
@endif
</div>
