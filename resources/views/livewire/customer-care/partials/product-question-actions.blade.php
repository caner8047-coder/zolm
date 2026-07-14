<div class="flex flex-col gap-2 items-stretch md:items-end">
    @if($question->learning_status === 'new')
        @if($eligibility['eligible'])
            <button type="button" wire:click="createKnowledgeCandidate({{ $question->id }})" wire:loading.attr="disabled"
                    class="w-full sm:w-auto px-4 py-3 sm:py-2 min-h-[44px] rounded-[6px] bg-slate-900 text-white text-xs font-medium hover:bg-slate-800 disabled:opacity-60">
                Bilgi Adayı Yap
            </button>
        @endif
        <button type="button" wire:click="excludeFromLearning({{ $question->id }})" wire:loading.attr="disabled"
                class="w-full sm:w-auto px-4 py-3 sm:py-2 min-h-[44px] rounded-[6px] border border-slate-200 bg-white text-slate-600 text-xs font-medium hover:bg-slate-50 disabled:opacity-60">
            Eğitim Dışı Bırak
        </button>
    @elseif($question->learning_status === 'candidate')
        <a href="{{ route('customer-care.suggestions', ['selectedStoreId' => $question->store_id, 'selectedStatus' => 'pending']) }}"
           class="w-full sm:w-auto px-4 py-3 sm:py-2 min-h-[44px] rounded-[6px] bg-slate-900 text-white text-xs font-medium hover:bg-slate-800 flex items-center justify-center">
            Adayı İncele
        </a>
        <button type="button" wire:click="excludeFromLearning({{ $question->id }})" wire:loading.attr="disabled"
                class="w-full sm:w-auto px-4 py-3 sm:py-2 min-h-[44px] rounded-[6px] border border-slate-200 bg-white text-slate-600 text-xs font-medium hover:bg-slate-50 disabled:opacity-60">
            Eğitim Dışı Bırak
        </button>
    @elseif($question->learning_status === 'applied')
        <button type="button" wire:click="toggleGoldenCandidate({{ $question->id }})" wire:loading.attr="disabled"
                class="w-full sm:w-auto px-4 py-3 sm:py-2 min-h-[44px] rounded-[6px] border {{ $question->is_golden_candidate ? 'border-violet-200 bg-violet-50 text-violet-800' : 'border-slate-200 bg-white text-slate-700' }} text-xs font-medium hover:bg-slate-50 disabled:opacity-60">
            {{ $question->is_golden_candidate ? 'Golden Adaydan Çıkar' : 'Golden Adayı Yap' }}
        </button>
    @elseif($question->learning_status === 'excluded')
        <button type="button" wire:click="restoreToLearning({{ $question->id }})" wire:loading.attr="disabled"
                class="w-full sm:w-auto px-4 py-3 sm:py-2 min-h-[44px] rounded-[6px] border border-slate-200 bg-white text-slate-700 text-xs font-medium hover:bg-slate-50 disabled:opacity-60">
            Yeniden İncele
        </button>
    @endif
</div>
