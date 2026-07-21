<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div><h1 class="text-2xl font-bold text-gray-900">Personel Belgeleri</h1><p class="text-gray-500 mt-1">{{ $documents->total() }} belge</p></div>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Çalışan ara..." class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <select wire:model.live="statusFilter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Tüm Durumlar</option>
                <option value="requested">Talep Edildi</option><option value="uploaded">Yüklendi</option><option value="active">Aktif</option>
                <option value="expired">Süresi Doldu</option><option value="rejected">Reddedildi</option>
            </select>
            <select wire:model.live="categoryFilter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Tüm Kategoriler</option>
                <option value="identity">Kimlik</option><option value="contract">Sözleşme</option><option value="health">Sağlık</option>
                <option value="certificate">Sertifika</option><option value="kvkk">KVKK</option>
            </select>
            <button wire:click="$set('search', ''); $set('statusFilter', null); $set('categoryFilter', null)" class="border border-gray-300 rounded-lg px-3 py-2 text-sm hover:bg-gray-50">Temizle</button>
        </div>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Çalışan</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Belge Türü</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kategori</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Durum</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Doğrulama</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Son Kullanma</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">İşlem</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($documents as $doc)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $doc->employee?->full_name ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $doc->documentType?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $doc->documentType?->category?->label() ?? '-' }}</td>
                        <td class="px-4 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $doc->status->color() }}-100 text-{{ $doc->status->color() }}-800">{{ $doc->status->label() }}</span></td>
                        <td class="px-4 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $doc->verification_status->color() }}-100 text-{{ $doc->verification_status->color() }}-800">{{ $doc->verification_status->label() }}</span></td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $doc->expiry_date?->format('d.m.Y') ?? '-' }}</td>
                        <td class="px-4 py-3 text-right text-sm"><a href="#" class="text-gray-600 hover:text-gray-900">Görüntüle</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Henüz belge bulunmuyor.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="flex justify-center">{{ $documents->links() }}</div>
</div>
