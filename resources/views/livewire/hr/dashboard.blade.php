<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">İnsan Kaynakları</h1>
            <p class="text-gray-500 mt-1">{{ $tenant->name }} — Modül yönetimi</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        @foreach($modules as $key => $module)
            <div class="bg-white rounded-lg border border-gray-200 p-6 hover:border-gray-300 transition-colors">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-medium text-gray-900">{{ $module['label'] }}</h3>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        Aktif
                    </span>
                </div>
                <p class="text-sm text-gray-500">{{ $module['label'] }} modülü aktif durumda.</p>
            </div>
        @endforeach
    </div>
</div>
