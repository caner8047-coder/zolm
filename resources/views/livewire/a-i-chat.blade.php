<div class="h-[calc(100vh-6rem)] flex flex-col">
    <!-- Page Header with Role Selection and Report Dropdown -->
    <div class="mb-3 lg:mb-4">
        <div>
            <h1 class="text-lg lg:text-2xl font-bold text-gray-900">E-ticaret, Üretim ve Operasyon Uzmanı</h1>
            <p class="text-gray-500 mt-1 text-xs lg:text-sm hidden sm:block">bu chat bot sistemdeki verilerle beslenmektedir. aldığı her komutu aldığı her görevi veya aldığı her soruyu önce anlar sistemdeki sipariş xls çıktılarını inceler ve ai sayesinde profesyonelce yorumlar</p>
        </div>
        
        <!-- Role Selection + Report Dropdown -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-3">
            <!-- Role buttons - horizontal scroll on mobile -->
            <div class="flex space-x-2 overflow-x-auto pb-1">
                <button 
                    wire:click="setRole('production')"
                    class="px-3 lg:px-4 py-2 text-xs lg:text-sm rounded-lg transition-colors whitespace-nowrap flex-shrink-0 {{ $role === 'production' ? 'bg-gray-900 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}"
                >
                    Üretim Müdürü
                </button>
                <button 
                    wire:click="setRole('operation')"
                    class="px-3 lg:px-4 py-2 text-xs lg:text-sm rounded-lg transition-colors whitespace-nowrap flex-shrink-0 {{ $role === 'operation' ? 'bg-gray-900 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}"
                >
                    Operasyon Sorumlusu
                </button>
                <button 
                    wire:click="setRole('expert')"
                    class="px-3 lg:px-4 py-2 text-xs lg:text-sm rounded-lg transition-colors whitespace-nowrap flex-shrink-0 {{ $role === 'expert' ? 'bg-gray-900 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}"
                >
                    Genel Uzman
                </button>
            </div>
            
            @if($this->recentReports->count() > 0)
            <select wire:model.live="selectedReportId" class="w-full sm:w-auto px-3 py-2 border border-gray-300 rounded-lg text-xs lg:text-sm">
                <option value="">Rapor seçin (opsiyonel)</option>
                @foreach($this->recentReports as $report)
                    <option value="{{ $report->id }}">{{ Str::limit($report->original_filename, 20) }} - {{ $report->created_at->format('d.m H:i') }}</option>
                @endforeach
            </select>
            @endif
        </div>
    </div>

    <!-- Chat Container - Expanded -->
    <div class="flex-1 bg-white rounded-lg border border-gray-200 flex flex-col overflow-hidden min-h-0">
        <!-- Messages -->
        <div class="flex-1 overflow-y-auto p-3 lg:p-6 space-y-3 lg:space-y-4">
            @foreach($messages as $msg)
            <div class="flex {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[90%] sm:max-w-[80%] {{ $msg['role'] === 'user' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-900' }} rounded-lg px-3 lg:px-4 py-2 lg:py-3">
                    <p class="text-xs lg:text-sm whitespace-pre-wrap">{!! nl2br(e($msg['content'])) !!}</p>
                </div>
            </div>
            @endforeach

            @if($isTyping)
            <div class="flex justify-start">
                <div class="bg-gray-100 rounded-lg px-4 py-3">
                    <div class="flex space-x-1">
                        <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></span>
                        <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></span>
                        <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></span>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Input -->
        <div class="border-t border-gray-200 p-3 lg:p-4">
            <form wire:submit="sendMessage" class="flex gap-2">
                <input 
                    type="text" 
                    wire:model="message"
                    placeholder="Mesajınızı yazın..."
                    class="flex-1 px-3 lg:px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent text-sm"
                >
                <button 
                    type="submit"
                    class="px-4 lg:px-6 py-3 bg-gray-900 text-white font-medium rounded-lg hover:bg-gray-800 transition-colors text-sm"
                >
                    Gönder
                </button>
                <button 
                    type="button"
                    wire:click="clearChat"
                    class="hidden sm:block px-3 lg:px-4 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors text-sm"
                >
                    Temizle
                </button>
            </form>
        </div>
    </div>

    <!-- Info Box - Compact (hidden on mobile) -->
    <div class="mt-2 bg-gray-50 rounded-lg border border-gray-200 p-2 lg:p-3 hidden sm:block">
        <p class="text-xs text-gray-500">
            <strong>ai chat:</strong> üretim ve operasyon listesi çıktılarını E-ticaret sorumlusu, operasyon sorumlusu ve üretim sorumlusu olarak analiz edip yorumla ve aksiyon planı oluştur.
        </p>
    </div>
</div>
