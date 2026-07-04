<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-slate-900">Gelen Kutusu</h1>
            <p class="text-sm text-slate-500 mt-1">Müşterilerden gelen WhatsApp mesajları</p>
        </div>
        @if($unreadCount > 0)
            <span class="px-2 py-1 text-xs font-bold bg-red-500 text-white rounded-full">{{ $unreadCount }}</span>
        @endif
    </div>

    {{-- Filtre --}}
    <div class="flex gap-2">
        @foreach(['all' => 'Tümü', 'open' => 'Açık', 'closed' => 'Kapalı'] as $value => $label)
            <button wire:click="setStatusFilter('{{ $value }}')"
                class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors
                    {{ $statusFilter === $value ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Konuşma Listesi --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Sol Panel: Konuşmalar --}}
        <div class="lg:col-span-1 rounded-[10px] border border-slate-200 bg-white shadow-sm overflow-hidden max-h-[600px] overflow-y-auto">
            @forelse($conversations as $conversation)
                <button wire:click="selectConversation({{ $conversation['id'] }})"
                    class="w-full text-left px-4 py-3 border-b border-slate-100 hover:bg-slate-50/50 transition-colors
                        {{ $selectedConversationId === $conversation['id'] ? 'bg-slate-50 border-l-2 border-l-slate-900' : '' }}">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-medium text-sm text-slate-900">
                                {{ $conversation['contact']['first_name'] ?? '' }}
                                {{ $conversation['contact']['last_name'] ?? '' }}
                            </div>
                            <div class="text-xs text-slate-500 mt-0.5">
                                {{ $conversation['contact']['phone_hash'] ? '***' . substr($conversation['contact']['phone_hash'], -6) : 'Bilinmeyen' }}
                            </div>
                        </div>
                        <div class="text-xs text-slate-400">
                            {{ $conversation['last_message_at'] ? \Carbon\Carbon::parse($conversation['last_message_at'])->diffForHumans() : '' }}
                        </div>
                    </div>
                </button>
            @empty
                <div class="px-4 py-8 text-center text-sm text-slate-400">
                    Henüz konuşma yok.
                </div>
            @endforelse
        </div>

        {{-- Sağ Panel: Mesajlar --}}
        <div class="lg:col-span-2 rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 min-h-[400px]">
            @if($selectedConversationId && count($messages) > 0)
                <div class="space-y-3 max-h-[500px] overflow-y-auto">
                    @foreach($messages as $msg)
                        <div class="flex {{ $msg['message_type'] === 'text' ? 'justify-start' : 'justify-end' }}">
                            <div class="max-w-[70%] rounded-lg px-3 py-2 text-sm
                                {{ $msg['message_type'] === 'text' ? 'bg-slate-100 text-slate-900' : 'bg-slate-900 text-white' }}">
                                @if($msg['body'])
                                    <p>{{ $msg['body'] }}</p>
                                @else
                                    <p class="italic opacity-70">[{{ $msg['message_type'] }}]</p>
                                @endif
                                <div class="text-[10px] mt-1 opacity-50">
                                    {{ \Carbon\Carbon::parse($msg['received_at'])->format('H:i') }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif($selectedConversationId)
                <div class="flex items-center justify-center h-full text-sm text-slate-400">
                    Bu konuşmada mesaj bulunamadı.
                </div>
            @else
                <div class="flex items-center justify-center h-full text-sm text-slate-400">
                    Bir konuşma seçin.
                </div>
            @endif
        </div>
    </div>
</div>
