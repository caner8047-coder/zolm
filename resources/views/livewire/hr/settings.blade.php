<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">İK Ayarları</h1>
            <p class="text-gray-500 mt-1">{{ $tenant->name }} — Sistem ayarları</p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200">
        <nav class="flex space-x-8">
            <button wire:click="$set('activeTab', 'general')"
                    class="py-2 px-1 border-b-2 text-sm font-medium {{ $activeTab === 'general' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Genel
            </button>
            <button wire:click="$set('activeTab', 'holidays'); $wire.loadHolidays()"
                    class="py-2 px-1 border-b-2 text-sm font-medium {{ $activeTab === 'holidays' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Tatiller
            </button>
        </nav>
    </div>

    @if (session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-green-700 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if ($activeTab === 'general')
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Genel Ayarlar</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tüzel Kişilik</label>
                    <p class="mt-1 text-sm text-gray-900">{{ $tenant->name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Vergi Numarası</label>
                    <p class="mt-1 text-sm text-gray-900">{{ $tenant->tax_number ?? 'Tanımlı değil' }}</p>
                </div>
            </div>
        </div>
    @endif

    @if ($activeTab === 'holidays')
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Tatil Yönetimi</h2>

            <!-- Yeni tatil ekleme formu -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tatil Adı</label>
                    <input type="text" wire:model="newHolidayName"
                           class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-gray-900 focus:border-gray-900"
                           placeholder="29 Ekim Cumhuriyet Bayramı">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tarih</label>
                    <input type="date" wire:model="newHolidayDate"
                           class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-gray-900 focus:border-gray-900">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tür</label>
                    <select wire:model="newHolidayType"
                            class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-gray-900 focus:border-gray-900">
                        <option value="national">Resmî Tatil</option>
                        <option value="religious">Dini Tatil</option>
                        <option value="special">Özel Gün</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button wire:click="addHoliday"
                            class="w-full bg-gray-900 text-white rounded-lg px-4 py-2 text-sm hover:bg-gray-800">
                        Ekle
                    </button>
                </div>
            </div>

            <!-- Tatil listesi -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tarih</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ad</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tür</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tekrarlanan</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($holidays as $holiday)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ \Carbon\Carbon::parse($holiday['date'])->format('d.m.Y') }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $holiday['name'] }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">
                                    @if($holiday['type'] === 'national') Resmî
                                    @elseif($holiday['type'] === 'religious') Dini
                                    @else Özel
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $holiday['is_recurring'] ? 'Evet' : 'Hayır' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <button wire:click="deleteHoliday({{ $holiday['id'] }})"
                                            wire:confirm="Bu tatili silmek istediğinize emin misiniz?"
                                            class="text-red-600 hover:text-red-800 text-sm">
                                        Sil
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">
                                    Henüz tatil tanımlanmamış.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
