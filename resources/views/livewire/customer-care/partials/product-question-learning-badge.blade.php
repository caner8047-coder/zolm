<div class="flex flex-wrap items-center gap-1.5">
    @if($question->learning_status === 'applied')
        <span class="px-2 py-0.5 text-xs font-mono rounded bg-emerald-100 text-emerald-800">BİLGİ TABANINDA</span>
    @elseif($question->learning_status === 'candidate')
        <span class="px-2 py-0.5 text-xs font-mono rounded bg-amber-100 text-amber-800">İNCELEME ADAYI</span>
    @elseif($question->learning_status === 'excluded')
        <span class="px-2 py-0.5 text-xs font-mono rounded bg-red-100 text-red-800">EĞİTİM DIŞI</span>
    @else
        <span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-100 text-slate-700">YENİ</span>
    @endif
    @if($question->is_golden_candidate)
        <span class="px-2 py-0.5 text-xs font-mono rounded bg-violet-100 text-violet-800">GOLDEN ADAY</span>
    @endif
</div>
