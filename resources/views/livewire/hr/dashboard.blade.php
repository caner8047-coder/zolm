<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">İnsan Kaynakları</h1>
            <p class="text-gray-500 mt-1">{{ $tenant->name }} — Modül yönetimi</p>
        </div>
    </div>

    @if(!empty($documentMetrics))
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
            <a href="{{ route('hr.documents', ['status' => 'requested']) }}" class="bg-white rounded-lg border border-gray-200 p-4 hover:border-gray-300">
                <p class="text-sm text-gray-500">Eksik Zorunlu Belge</p>
                <p class="text-2xl font-bold {{ $documentMetrics['missing_mandatory'] > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $documentMetrics['missing_mandatory'] }}</p>
            </a>
            <a href="{{ route('hr.documents') }}" class="bg-white rounded-lg border border-gray-200 p-4 hover:border-gray-300">
                <p class="text-sm text-gray-500">30 Gün İçinde Dolacak</p>
                <p class="text-2xl font-bold {{ $documentMetrics['expiring_soon'] > 0 ? 'text-orange-600' : 'text-gray-900' }}">{{ $documentMetrics['expiring_soon'] }}</p>
            </a>
            <a href="{{ route('hr.documents', ['status' => 'expired']) }}" class="bg-white rounded-lg border border-gray-200 p-4 hover:border-gray-300">
                <p class="text-sm text-gray-500">Süresi Dolmuş</p>
                <p class="text-2xl font-bold {{ $documentMetrics['expired'] > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $documentMetrics['expired'] }}</p>
            </a>
            <a href="{{ route('hr.documents') }}" class="bg-white rounded-lg border border-gray-200 p-4 hover:border-gray-300">
                <p class="text-sm text-gray-500">Doğrulama Bekleyen</p>
                <p class="text-2xl font-bold {{ $documentMetrics['pending_verification'] > 0 ? 'text-yellow-600' : 'text-gray-900' }}">{{ $documentMetrics['pending_verification'] }}</p>
            </a>
            <a href="{{ route('hr.documents') }}" class="bg-white rounded-lg border border-gray-200 p-4 hover:border-gray-300">
                <p class="text-sm text-gray-500">Geciken Belge Talebi</p>
                <p class="text-2xl font-bold {{ $documentMetrics['overdue_requests'] > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $documentMetrics['overdue_requests'] }}</p>
            </a>
        </div>
    @endif

    @if(!empty($leaveMetrics))
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="{{ route('hr.leaves.approvals') }}" class="bg-white rounded-lg border border-slate-200 p-4 hover:border-slate-300"><p class="text-sm text-slate-500">Onay Bekleyen İzin</p><p class="text-2xl font-bold {{ $leaveMetrics['pending_approval'] > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ $leaveMetrics['pending_approval'] }}</p></a>
            <a href="{{ route('hr.leaves') }}" class="bg-white rounded-lg border border-slate-200 p-4 hover:border-slate-300"><p class="text-sm text-slate-500">Bugün İzinli</p><p class="text-2xl font-bold text-slate-900">{{ $leaveMetrics['today_approved'] }}</p></a>
            <a href="{{ route('hr.leaves') }}" class="bg-white rounded-lg border border-slate-200 p-4 hover:border-slate-300"><p class="text-sm text-slate-500">Yaklaşan Onaylı İzin</p><p class="text-2xl font-bold text-slate-900">{{ $leaveMetrics['upcoming_approved'] }}</p></a>
            <a href="{{ route('hr.leaves.balances') }}" class="bg-white rounded-lg border border-slate-200 p-4 hover:border-slate-300"><p class="text-sm text-slate-500">Eksi Bakiye</p><p class="text-2xl font-bold {{ $leaveMetrics['negative_balances'] > 0 ? 'text-red-600' : 'text-slate-900' }}">{{ $leaveMetrics['negative_balances'] }}</p></a>
        </div>
    @endif

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
