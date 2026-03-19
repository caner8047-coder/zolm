<div>
    <div class="mb-6 lg:mb-8">
        <h1 class="text-xl lg:text-2xl font-bold text-gray-900">Özel Motor</h1>
        <p class="text-gray-500 mt-1 text-sm lg:text-base">Kendi profiliniz ile giriş dosyasını istediğiniz formata dönüştürün</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 lg:gap-8">
        <div class="xl:col-span-2 space-y-4 lg:space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 p-4 lg:p-6 shadow-sm">
                <h3 class="text-sm font-medium text-gray-500 mb-4">import</h3>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 sm:gap-4">
                    <div class="relative w-full sm:w-auto">
                        <select wire:model.live="selectedProfileId" class="w-full sm:w-auto appearance-none px-4 py-3 pr-10 border border-gray-300 rounded-lg text-base sm:text-sm bg-white">
                            @forelse($this->profiles as $profile)
                                <option value="{{ $profile->id }}">
                                    {{ $profile->name }} @if($profile->is_default) ★ @endif
                                </option>
                            @empty
                                <option value="">Profil bulunamadı</option>
                            @endforelse
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>

                    <label class="flex-1 flex items-center justify-center px-4 lg:px-6 py-4 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-gray-400 transition-colors">
                        <input type="file" wire:model="file" accept=".xlsx,.xls" class="hidden">
                        <div class="flex items-center space-x-2 text-sm text-gray-600">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <span class="truncate">{{ $file ? $file->getClientOriginalName() : 'girdi xls/xlsx dosyası seçin' }}</span>
                        </div>
                    </label>

                    <button
                        wire:click="process"
                        wire:loading.attr="disabled"
                        class="w-full sm:w-auto px-4 py-3 bg-gray-900 text-white font-medium rounded-lg hover:bg-gray-800 disabled:opacity-50 transition-colors"
                    >
                        <span wire:loading.remove wire:target="process">Dönüştür</span>
                        <span wire:loading wire:target="process">İşleniyor...</span>
                    </button>
                </div>

                @error('file')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            @if($isProcessing)
            <div class="bg-white rounded-xl border border-gray-200 p-8 text-center shadow-sm">
                <div class="flex justify-center space-x-1 mb-4">
                    <span class="w-3 h-3 bg-gray-900 rounded-full animate-bounce"></span>
                    <span class="w-3 h-3 bg-gray-900 rounded-full animate-bounce" style="animation-delay: 0.1s"></span>
                    <span class="w-3 h-3 bg-gray-900 rounded-full animate-bounce" style="animation-delay: 0.2s"></span>
                </div>
                <p class="text-gray-600">Özel motor çalışıyor...</p>
            </div>
            @endif

            @if($message)
            <div class="p-4 rounded-lg {{ $messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700' }}">
                {{ $message }}
            </div>
            @endif

            @if(count($generatedFiles) > 0)
            <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-200 shadow-sm">
                @foreach($generatedFiles as $index => $file)
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-900 truncate">{{ $index + 1 }}. {{ $file['filename'] }}</p>
                        <p class="text-xs text-gray-500">Özel motor çıktısı</p>
                    </div>
                    <a
                        href="{{ route('download', $file['id']) }}"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        xls dışarı aktar
                    </a>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        <div class="space-y-4 lg:space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <a href="{{ route('custom-motors.create') }}" class="block w-full px-4 py-3 text-center bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    + Özel Profil Oluştur
                </a>
                <p class="text-xs text-gray-400 mt-2 text-center">Örnek dosyaları verip AI ile profil üretin</p>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <a href="{{ route('reports') }}" class="block w-full px-4 py-2 text-sm text-center border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    Geçmiş Raporlar
                </a>
            </div>
        </div>
    </div>
</div>
