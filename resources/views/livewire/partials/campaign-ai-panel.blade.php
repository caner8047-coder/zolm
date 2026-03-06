{{-- AI Analiz Paneli - Kampanya Modülleri İçin Paylaşılan Yüzen (Floating) Panel Partial --}}
{{-- Kullanım: @include('livewire.partials.campaign-ai-panel', ['themeColor' => 'emerald', 'moduleName' => 'Plus']) --}}

@php $themeColor = $themeColor ?? 'emerald'; @endphp

@if($this->activeReport)
    <div x-data="{ 
            isAIPanelOpen: false, 
            activeTab: 'campaign', // 'campaign', 'loss', 'chat'
            init() {
                // Livewire'dan gelen olayları dinle
                window.addEventListener('openAiPanel', (e) => {
                    this.isAIPanelOpen = true;
                    if(e.detail && e.detail.tab) {
                        this.activeTab = e.detail.tab;
                    }
                });
            }
        }">



        {{-- Floating Offcanvas Panel (Sağdan Açılan Pencereli) --}}
        <div x-show="isAIPanelOpen" 
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="translate-x-full opacity-0"
             x-transition:enter-end="translate-x-0 opacity-100"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="translate-x-0 opacity-100"
             x-transition:leave-end="translate-x-full opacity-0"
             class="fixed inset-y-0 right-0 w-full max-w-md bg-white shadow-2xl z-50 flex flex-col hidden sm:w-[500px]"
             :class="{ 'hidden': !isAIPanelOpen }"
             style="display: none;">

            {{-- Header --}}
            <div class="px-5 py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-white flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2">
                    <span class="text-2xl">🤖</span>
                    <div>
                        <h2 class="text-lg font-bold text-gray-800">AI Zeka Merkezi</h2>
                        <p class="text-[10px] text-gray-500 font-medium tracking-wide uppercase">Rapor: {{ \Illuminate\Support\Str::limit($this->activeReport->original_filename, 20) }}</p>
                    </div>
                </div>
                <button @click="isAIPanelOpen = false" class="p-2 text-gray-400 hover:text-red-500 rounded-full hover:bg-red-50 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Tabs --}}
            <div class="flex w-full bg-gray-50 border-b border-gray-200 p-2 gap-2">
                <button @click="activeTab = 'campaign'" 
                        :class="{ 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-md': activeTab === 'campaign', 'bg-white text-gray-600 hover:bg-gray-100 border border-transparent hover:border-gray-200': activeTab !== 'campaign' }"
                        class="flex-1 py-2 text-xs font-bold rounded-lg transition-all flex items-center justify-center gap-1.5">
                    🤖 Kampanya Analizi
                </button>
                <button @click="activeTab = 'loss'" 
                        :class="{ 'bg-gradient-to-r from-red-500 to-rose-600 text-white shadow-md': activeTab === 'loss', 'bg-white text-gray-600 hover:bg-gray-100 border border-transparent hover:border-gray-200': activeTab !== 'loss' }"
                        class="flex-1 py-2 text-xs font-bold rounded-lg transition-all flex items-center justify-center gap-1.5">
                    🔴 Zarar Denetimi
                </button>
                <button @click="activeTab = 'chat'" 
                        :class="{ 'bg-blue-600 text-white shadow-md': activeTab === 'chat', 'bg-white text-gray-600 hover:bg-gray-100 border border-transparent hover:border-gray-200': activeTab !== 'chat' }"
                        class="px-4 py-2 text-xs font-bold rounded-lg transition-all flex items-center justify-center gap-1.5">
                    💬 Chat
                </button>
            </div>

            {{-- Content Area --}}
            <div class="flex-1 overflow-y-auto bg-gray-50/50 relative">

                {{-- 1. KAMPANYA ANALİZİ TAB'ı --}}
                <div x-show="activeTab === 'campaign'" x-transition.opacity class="p-5">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-indigo-900 flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            Stratejik Kampanya Analizi
                        </h3>
                        <button wire:click="generateAIAnalysis" wire:loading.attr="disabled" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-full flex items-center gap-1 transition-colors">
                            <span wire:loading.remove wire:target="generateAIAnalysis">🔄 Yenile</span>
                            <span wire:loading wire:target="generateAIAnalysis" class="animate-spin">⏳</span>
                        </button>
                    </div>

                    @if($this->activeReport->ai_analysis)
                        <div class="bg-white border border-indigo-100 rounded-xl p-4 shadow-sm relative">
                            <div wire:loading wire:target="generateAIAnalysis" class="absolute inset-0 bg-white/80 backdrop-blur-sm rounded-xl z-10 flex flex-col items-center justify-center">
                                <div class="w-8 h-8 rounded-full border-2 border-indigo-200 border-t-indigo-600 animate-spin"></div>
                                <span class="mt-2 text-xs font-bold text-indigo-600">Analiz Ediliyor...</span>
                            </div>
                            <div class="prose prose-sm max-w-none text-gray-700 text-sm leading-relaxed marker:text-indigo-500">
                                {!! \Illuminate\Support\Str::markdown($this->activeReport->ai_analysis) !!}
                            </div>
                        </div>
                    @else
                        <div class="text-center py-12 flex flex-col items-center justify-center">
                            <div class="w-16 h-16 bg-indigo-50 text-indigo-500 rounded-full flex items-center justify-center text-3xl mb-4">✨</div>
                            <h4 class="text-gray-900 font-bold mb-2">Henüz Analiz Yapılmadı</h4>
                            <p class="text-sm text-gray-500 mb-6 max-w-xs text-center">Yapay zeka, ürünlerinizin marjlarını inceleyerek size stratejik kararlar sunabilir.</p>
                            <button wire:click="generateAIAnalysis" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg shadow-md transition-colors flex items-center gap-2">
                                <span wire:loading.remove wire:target="generateAIAnalysis">Analizi Başlat</span>
                                <span wire:loading wire:target="generateAIAnalysis" class="animate-spin">⏳</span>
                            </button>
                        </div>
                    @endif
                </div>

                {{-- 2. ZARAR DENETİMİ TAB'ı --}}
                <div x-show="activeTab === 'loss'" x-transition.opacity class="p-5" style="display: none;">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-red-900 flex items-center gap-2">
                            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            Zarar Denetim Raporu
                        </h3>
                        <button wire:click="analyzeLosses" wire:loading.attr="disabled" class="text-xs font-semibold text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-full flex items-center gap-1 transition-colors">
                            <span wire:loading.remove wire:target="analyzeLosses">🔄 Yenile</span>
                            <span wire:loading wire:target="analyzeLosses" class="animate-spin">⏳</span>
                        </button>
                    </div>

                    @if($this->activeReport->loss_analysis)
                        <div class="bg-white border border-red-100 rounded-xl p-4 shadow-sm relative">
                            <div wire:loading wire:target="analyzeLosses" class="absolute inset-0 bg-white/80 backdrop-blur-sm rounded-xl z-10 flex flex-col items-center justify-center">
                                <div class="w-8 h-8 rounded-full border-2 border-red-200 border-t-red-600 animate-spin"></div>
                                <span class="mt-2 text-xs font-bold text-red-600">Denetleniyor...</span>
                            </div>
                            <div class="prose prose-sm max-w-none text-gray-700 text-sm leading-relaxed marker:text-red-500">
                                {!! \Illuminate\Support\Str::markdown($this->activeReport->loss_analysis) !!}
                            </div>
                        </div>
                    @else
                        <div class="text-center py-12 flex flex-col items-center justify-center">
                            <div class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center text-3xl mb-4">📉</div>
                            <h4 class="text-gray-900 font-bold mb-2">Zarar Denetimi Yapılmadı</h4>
                            <p class="text-sm text-gray-500 mb-6 max-w-xs text-center">Görünmeyen maliyetler, komisyonlar ve kargo desileri yüzünden zarar eden ürünleri saptayın.</p>
                            <button wire:click="analyzeLosses" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-lg shadow-md transition-colors flex items-center gap-2">
                                <span wire:loading.remove wire:target="analyzeLosses">Denetimi Başlat</span>
                                <span wire:loading wire:target="analyzeLosses" class="animate-spin">⏳</span>
                            </button>
                        </div>
                    @endif
                </div>

                {{-- 3. CHAT TAB'ı --}}
                <div x-show="activeTab === 'chat'" x-transition.opacity class="flex flex-col h-full absolute inset-0 bg-white" style="display: none;">
                    
                    {{-- Chat Messages --}}
                    <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-gray-50/50">
                        @if($this->chatConversation && count($this->chatConversation->messages ?? []) > 0)
                            @foreach($this->chatConversation->messages as $msg)
                                @if($msg['role'] === 'user')
                                    <div class="flex justify-end">
                                        <div class="bg-blue-600 text-white text-sm rounded-2xl rounded-tr-sm px-4 py-2.5 max-w-[85%] shadow-sm">{{ $msg['content'] }}</div>
                                    </div>
                                @else
                                    <div class="flex justify-start">
                                        <div class="bg-white border border-gray-200 text-gray-800 text-sm rounded-2xl rounded-tl-sm px-4 py-3 max-w-[90%] shadow-sm prose prose-sm marker:text-blue-500">
                                            {!! \Illuminate\Support\Str::markdown($msg['content']) !!}
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @else
                            <div class="flex flex-col flex-1 items-center justify-center text-center opacity-60 mt-10">
                                <span class="text-4xl mb-3">💬</span>
                                <h4 class="font-bold text-gray-700">Rapor Asistanı</h4>
                                <p class="text-sm text-gray-500 max-w-xs mt-1">Görüntülediğiniz analize dair her şeyi sorabilirsiniz. (Örn: "Karışık renk puf için ne yapmalıyım?")</p>
                            </div>
                        @endif
                        
                        @if($isChatting)
                            <div class="flex justify-start">
                                <div class="bg-white border border-gray-200 text-gray-500 text-sm rounded-2xl rounded-tl-sm px-4 py-3 shadow-sm flex items-center gap-2">
                                    <div class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                                    <div class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                                    <div class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                                </div>
                            </div>
                        @endif
                    </div>
                
                    {{-- Chat Input --}}
                    <div class="border-t border-gray-200 bg-white p-3 shadow-[0_-4px_10px_rgba(0,0,0,0.02)] z-10">
                        <div class="flex gap-2">
                            <input wire:model="chatMessage" wire:keydown.enter="sendMessage" type="text" placeholder="Ürün veya kârlılık hakkında soru sorun..."
                                class="flex-1 border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-shadow bg-gray-50 focus:bg-white placeholder-gray-400" />
                            <button wire:click="sendMessage" wire:loading.attr="disabled"
                                class="px-5 py-3 bg-blue-600 text-white font-bold rounded-xl shadow-md hover:bg-blue-700 hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center">
                                <span wire:loading.remove wire:target="sendMessage">
                                    <svg class="w-5 h-5 -rotate-90 transform" fill="currentColor" viewBox="0 0 20 20"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/></svg>
                                </span>
                                <span wire:loading wire:target="sendMessage" class="animate-spin w-5 h-5 border-2 border-white border-t-transparent rounded-full"></span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
@endif
