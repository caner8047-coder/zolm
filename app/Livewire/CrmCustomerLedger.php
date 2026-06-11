<?php

namespace App\Livewire;

use App\Models\CrmContact;
use App\Models\CrmCustomerLedgerEntry;
use App\Models\MarketplaceStore;
use App\Models\Recipe;
use App\Services\Crm\CrmCustomerLedgerProjectionService;
use App\Services\Crm\CrmProjectionService;
use App\Services\ExcelService;
use App\Services\MpSettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class CrmCustomerLedger extends Component
{
    use WithPagination;

    public array $visibleColumns = ['musteri', 'urun', 'platform', 'tarife', 'tutar', 'komisyon', 'kar', 'durum', 'aksiyon'];

    public static array $sortableColumns = [
        'musteri' => 'contact_id',
        'urun' => 'product_name',
        'platform' => 'platform',
        'tarife' => 'tariff_name',
        'tarih' => 'purchased_at',
        'tutar' => 'gross_amount',
        'komisyon' => 'commission_amount',
        'net' => 'net_amount',
        'kar' => 'profit_amount',
        'durum' => 'status',
    ];

    public static array $allColumnDefs = [
        'musteri' => 'Müşteri',
        'urun' => 'Ürün',
        'platform' => 'Platform',
        'tarife' => 'Tarife',
        'tarih' => 'Tarih',
        'tutar' => 'Tutar',
        'komisyon' => 'Komisyon',
        'net' => 'Net',
        'kar' => 'Kâr',
        'durum' => 'Durum',
        'aksiyon' => 'Aksiyon',
    ];

    public string $search = '';
    public string $platformFilter = '';
    public string $storeFilter = '';
    public string $statusFilter = '';
    public string $dateFilter = '90';
    public string $marginFilter = '';
    public string $sortField = 'purchased_at';
    public string $sortDirection = 'desc';
    public ?int $selectedContactId = null;
    public ?int $editingEntryId = null;
    public string $ledgerMessage = '';
    public string $ledgerMessageTone = 'success';
    public array $entryForm = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'platformFilter' => ['except' => ''],
        'storeFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'dateFilter' => ['except' => '90'],
        'marginFilter' => ['except' => ''],
        'selectedContactId' => ['except' => null, 'as' => 'contact'],
    ];

    public function mount(): void
    {
        $this->entryForm = $this->blankEntryForm();

        if (!$this->ledgerTablesReady()) {
            return;
        }

        $this->visibleColumns = $this->normalizeVisibleColumns(
            app(MpSettingsService::class)->getArray('crm.customer_ledger.visible_columns', $this->visibleColumns)
        );

        if ($this->selectedContactId && !$this->contactBelongsToCurrentUser($this->selectedContactId)) {
            $this->selectedContactId = null;
        }

        if ($this->selectedContactId) {
            $this->entryForm['contact_id'] = (string) $this->selectedContactId;
        }
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'platformFilter', 'storeFilter', 'statusFilter', 'dateFilter', 'marginFilter'], true)) {
            $this->resetPage();
        }
    }

    public function syncFromOrders(CrmProjectionService $projectionService): void
    {
        if (!$this->ledgerTablesReady()) {
            $this->notify('Müşteri cari tabloları hazır değil. Önce migration çalıştırın.', 'warning');
            return;
        }

        $summary = $projectionService->projectUser(auth()->user(), [
            'sources' => ['orders'],
            'recent_days' => 30,
        ]);

        if (($summary['skipped'] ?? 0) > 0) {
            $this->notify('CRM tabloları hazır değil. Önce migration çalıştırın.', 'warning');
            return;
        }

        $this->notify("Son 30 gün siparişleri CRM’e işlendi: {$summary['events']} olay, " . ($summary['ledger_entries'] ?? 0) . ' cari hareket.');
    }

    public function saveManualEntry(CrmCustomerLedgerProjectionService $ledgerService): void
    {
        if (!$this->ledgerTablesReady()) {
            $this->notify('Müşteri cari tabloları hazır değil. Önce migration çalıştırın.', 'warning');
            return;
        }

        $this->validate($this->rules(), [], $this->validationAttributes());
        $wasEditing = $this->editingEntryId !== null;

        try {
            $entry = $this->editingEntryId
                ? $ledgerService->updateManualEntry(auth()->user(), $this->editingEntryId, $this->entryForm)
                : $ledgerService->createManualEntry(auth()->user(), $this->entryForm);
        } catch (ModelNotFoundException) {
            $this->editingEntryId = null;
            $this->notify('Düzenlenecek manuel cari hareketi bulunamadı.', 'warning');
            return;
        }

        $this->selectedContactId = $entry->contact_id;
        $this->editingEntryId = null;
        $this->entryForm = $this->blankEntryForm((int) $entry->contact_id);
        $this->notify('Müşteri cari hareketi ' . ($wasEditing ? 'güncellendi' : 'kaydedildi') . ' ve CRM 360 timeline güncellendi.');
        $this->resetPage();
    }

    public function exportLedger(ExcelService $excelService)
    {
        if (!$this->ledgerTablesReady()) {
            $this->notify('Müşteri cari tabloları hazır değil. Önce migration çalıştırın.', 'warning');
            return null;
        }

        $rows = $this->buildEntriesQuery()
            ->reorder('purchased_at', 'desc')
            ->limit(5000)
            ->get()
            ->map(fn (CrmCustomerLedgerEntry $entry) => [
                'Müşteri' => $entry->contact?->display_name ?? '',
                'Telefon' => $entry->contact?->primary_phone ?? '',
                'E-posta' => $entry->contact?->primary_email ?? '',
                'Platform' => $entry->platform ?? '',
                'Mağaza' => $entry->store?->store_name ?? '',
                'Sipariş No' => $entry->marketplace_order_number ?? '',
                'Tarih' => $entry->purchased_at?->format('d.m.Y H:i') ?? '',
                'Ürün' => $entry->product_name,
                'Stok Kodu' => $entry->stock_code ?? '',
                'Barkod' => $entry->barcode ?? '',
                'Reçete' => trim((string) ($entry->recipe_name ?? '') . ' ' . (string) ($entry->recipe_version ?? '')),
                'Tarife' => $entry->tariff_name ?? '',
                'Adet' => (float) $entry->quantity,
                'Birim Fiyat' => (float) $entry->unit_price,
                'Brüt Tutar' => (float) $entry->gross_amount,
                'İndirim' => (float) $entry->discount_amount,
                'Komisyon Oranı (%)' => (float) $entry->commission_rate,
                'Komisyon Tutarı' => (float) $entry->commission_amount,
                'Kargo' => (float) $entry->cargo_amount,
                'Maliyet' => (float) $entry->cost_amount,
                'Net Tutar' => (float) $entry->net_amount,
                'Kâr' => (float) $entry->profit_amount,
                'Durum' => $entry->statusLabel(),
                'Kaynak' => $entry->sourceLabel(),
                'Not' => $entry->notes ?? '',
            ])
            ->values()
            ->all();

        if ($rows === []) {
            $rows = [[
                'Müşteri' => 'Veri yok',
                'Telefon' => '',
                'E-posta' => '',
                'Platform' => '',
                'Mağaza' => '',
                'Sipariş No' => '',
                'Tarih' => '',
                'Ürün' => '',
                'Stok Kodu' => '',
                'Barkod' => '',
                'Reçete' => '',
                'Tarife' => '',
                'Adet' => 0,
                'Birim Fiyat' => 0,
                'Brüt Tutar' => 0,
                'İndirim' => 0,
                'Komisyon Oranı (%)' => 0,
                'Komisyon Tutarı' => 0,
                'Kargo' => 0,
                'Maliyet' => 0,
                'Net Tutar' => 0,
                'Kâr' => 0,
                'Durum' => '',
                'Kaynak' => '',
                'Not' => '',
            ]];
        }

        $fileName = 'musteri-cari-' . now()->format('Ymd-His') . '.xlsx';
        $path = storage_path('app/temp/' . $fileName);

        $excelService->exportToXlsx([
            [
                'name' => 'Musteri Cari',
                'data' => $rows,
            ],
        ], $path);

        return response()->download($path, $fileName)->deleteFileAfterSend(true);
    }

    public function selectContact(int $contactId): void
    {
        if (!$this->contactBelongsToCurrentUser($contactId)) {
            return;
        }

        $this->selectedContactId = $contactId;
        if (!$this->editingEntryId) {
            $this->entryForm['contact_id'] = (string) $contactId;
        }
        $this->resetPage();
    }

    public function clearContactFilter(): void
    {
        $this->selectedContactId = null;
        if (!$this->editingEntryId) {
            $this->entryForm['contact_id'] = '';
        }
        $this->resetPage();
    }

    public function editEntry(int $entryId): void
    {
        if (!$this->ledgerTablesReady()) {
            $this->notify('Müşteri cari tabloları hazır değil. Önce migration çalıştırın.', 'warning');
            return;
        }

        $entry = CrmCustomerLedgerEntry::query()
            ->where('user_id', auth()->id())
            ->whereKey($entryId)
            ->with('contact')
            ->first();

        if (!$entry) {
            $this->notify('Cari hareket bulunamadı.', 'warning');
            return;
        }

        if ($entry->source_type !== 'manual') {
            $this->notify('Pazaryeri kaynaklı hareketler sipariş projeksiyonundan güncellenir; manuel olarak düzenlenemez.', 'warning');
            return;
        }

        $this->editingEntryId = $entry->id;
        $this->selectedContactId = $entry->contact_id;
        $this->entryForm = [
            'contact_id' => (string) $entry->contact_id,
            'customer_name' => $entry->contact?->display_name ?? '',
            'customer_phone' => $entry->contact?->primary_phone ?? '',
            'customer_email' => $entry->contact?->primary_email ?? '',
            'store_id' => $entry->store_id ? (string) $entry->store_id : '',
            'platform' => $entry->platform ?? '',
            'marketplace_order_number' => $entry->marketplace_order_number ?? '',
            'product_name' => $entry->product_name ?? '',
            'stock_code' => $entry->stock_code ?? '',
            'barcode' => $entry->barcode ?? '',
            'recipe_id' => $entry->recipe_id ? (string) $entry->recipe_id : '',
            'tariff_name' => $entry->tariff_name ?? '',
            'quantity' => (float) $entry->quantity,
            'unit_price' => (float) $entry->unit_price,
            'discount_amount' => (float) $entry->discount_amount,
            'commission_rate' => (float) $entry->commission_rate,
            'commission_amount' => '',
            'cargo_amount' => (float) $entry->cargo_amount,
            'cost_amount' => (float) $entry->cost_amount > 0 ? (float) $entry->cost_amount : '',
            'status' => $entry->status ?: 'completed',
            'purchased_at' => $entry->purchased_at?->format('Y-m-d\TH:i') ?: now()->format('Y-m-d\TH:i'),
            'notes' => $entry->notes ?? '',
        ];

        $this->notify('Manuel cari hareketi düzenleme moduna alındı.');
        $this->resetPage();
    }

    public function cancelEdit(): void
    {
        $this->editingEntryId = null;
        $this->entryForm = $this->blankEntryForm($this->selectedContactId);
    }

    public function voidManualEntry(int $entryId, CrmCustomerLedgerProjectionService $ledgerService): void
    {
        if (!$this->ledgerTablesReady()) {
            $this->notify('Müşteri cari tabloları hazır değil. Önce migration çalıştırın.', 'warning');
            return;
        }

        try {
            $entry = $ledgerService->voidManualEntry(auth()->user(), $entryId);
        } catch (ModelNotFoundException) {
            $this->notify('İptal edilecek manuel cari hareketi bulunamadı.', 'warning');
            return;
        }

        if ($this->editingEntryId === $entryId) {
            $this->cancelEdit();
        }

        $this->selectedContactId = $entry->contact_id;
        $this->entryForm['contact_id'] = (string) $entry->contact_id;
        $this->notify('Manuel cari hareketi iptal edildi ve CRM 360 özeti güncellendi.');
        $this->resetPage();
    }

    public function sortTable(string $columnKey): void
    {
        $field = static::$sortableColumns[$columnKey] ?? null;

        if (!$field) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = in_array($field, ['product_name', 'platform', 'tariff_name', 'status'], true) ? 'asc' : 'desc';
        }

        $this->resetPage();
    }

    public function toggleColumn(string $column): void
    {
        if (!array_key_exists($column, static::$allColumnDefs)) {
            return;
        }

        if (in_array($column, $this->visibleColumns, true)) {
            if (count($this->visibleColumns) === 1) {
                return;
            }

            $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$column]));
        } else {
            $this->visibleColumns[] = $column;
            $this->visibleColumns = $this->normalizeVisibleColumns($this->visibleColumns);
        }

        app(MpSettingsService::class)->set('crm.customer_ledger.visible_columns', $this->visibleColumns);
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->platformFilter = '';
        $this->storeFilter = '';
        $this->statusFilter = '';
        $this->dateFilter = '90';
        $this->marginFilter = '';
        $this->selectedContactId = null;
        if (!$this->editingEntryId) {
            $this->entryForm['contact_id'] = '';
        }
        $this->resetPage();
    }

    public function statusOptions(): array
    {
        return [
            'completed' => 'Tamamlandı',
            'pending' => 'Bekliyor',
            'returned' => 'İade',
            'cancelled' => 'İptal',
        ];
    }

    public function dateOptions(): array
    {
        return [
            '30' => 'Son 30 gün',
            '90' => 'Son 90 gün',
            '180' => 'Son 180 gün',
            '365' => 'Son 1 yıl',
            'all' => 'Tümü',
        ];
    }

    public function marginOptions(): array
    {
        return [
            'negative' => 'Zararlı',
            'low' => 'Düşük kâr',
            'healthy' => 'Sağlıklı',
        ];
    }

    public function sourceActionForEntry(CrmCustomerLedgerEntry $entry): ?array
    {
        if ($entry->channel_order_id && $entry->marketplace_order_number) {
            return [
                'label' => 'Siparişi Aç',
                'url' => route('mp.orders', ['search' => $entry->marketplace_order_number]),
            ];
        }

        return $entry->contact_id ? [
            'label' => 'CRM 360',
            'url' => route('crm.workspace', ['contact' => $entry->contact_id]),
        ] : null;
    }

    public function render()
    {
        if (!$this->ledgerTablesReady()) {
            return view('livewire.crm-customer-ledger', [
                'ledgerReady' => false,
                'entries' => collect(),
                'stats' => $this->emptyStats(),
                'stores' => collect(),
                'contacts' => collect(),
                'recipes' => collect(),
                'platforms' => collect(),
                'selectedContact' => null,
                'selectedEntries' => collect(),
                'selectedContactStats' => $this->emptyStats(),
                'activeFilters' => [],
                'columnDefs' => static::$allColumnDefs,
                'sortableColumns' => static::$sortableColumns,
            ])->layout('layouts.app', ['title' => 'Müşteri Cari']);
        }

        $entries = $this->buildEntriesQuery()
            ->reorder($this->sortField, $this->sortDirection)
            ->orderByDesc('purchased_at')
            ->paginate(25);

        return view('livewire.crm-customer-ledger', [
            'ledgerReady' => true,
            'entries' => $entries,
            'stats' => $this->stats(),
            'stores' => $this->stores(),
            'contacts' => $this->contacts(),
            'recipes' => $this->recipes(),
            'platforms' => $this->platforms(),
            'selectedContact' => $this->selectedContact(),
            'selectedEntries' => $this->selectedEntries(),
            'selectedContactStats' => $this->selectedContactStats(),
            'activeFilters' => $this->activeFilters(),
            'columnDefs' => static::$allColumnDefs,
            'sortableColumns' => static::$sortableColumns,
        ])->layout('layouts.app', ['title' => 'Müşteri Cari']);
    }

    protected function buildEntriesQuery(): Builder
    {
        return CrmCustomerLedgerEntry::query()
            ->where('user_id', auth()->id())
            ->with(['contact', 'store', 'recipe'])
            ->when($this->selectedContactId, fn (Builder $query) => $query->where('contact_id', $this->selectedContactId))
            ->when($this->search !== '', function (Builder $query) {
                $term = '%' . trim($this->search) . '%';
                $query->where(function (Builder $searchQuery) use ($term) {
                    $searchQuery
                        ->where('product_name', 'like', $term)
                        ->orWhere('stock_code', 'like', $term)
                        ->orWhere('barcode', 'like', $term)
                        ->orWhere('tariff_name', 'like', $term)
                        ->orWhere('marketplace_order_number', 'like', $term)
                        ->orWhereHas('contact', fn (Builder $contactQuery) => $contactQuery
                            ->where('display_name', 'like', $term)
                            ->orWhere('primary_phone', 'like', $term)
                            ->orWhere('primary_email', 'like', $term));
                });
            })
            ->when($this->platformFilter !== '', fn (Builder $query) => $query->where('platform', $this->platformFilter))
            ->when($this->storeFilter !== '', fn (Builder $query) => $query->where('store_id', (int) $this->storeFilter))
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->dateFilter !== 'all', fn (Builder $query) => $query->where('purchased_at', '>=', now()->subDays((int) $this->dateFilter)))
            ->when($this->marginFilter === 'negative', fn (Builder $query) => $query->where('profit_amount', '<', 0))
            ->when($this->marginFilter === 'low', fn (Builder $query) => $query->whereBetween('profit_amount', [0, 99.99]))
            ->when($this->marginFilter === 'healthy', fn (Builder $query) => $query->where('profit_amount', '>=', 100));
    }

    protected function stats(): array
    {
        $query = CrmCustomerLedgerEntry::query()->where('user_id', auth()->id());
        $financialQuery = (clone $query)->whereNotIn('status', ['cancelled', 'returned']);
        $gross = (float) (clone $financialQuery)->sum('gross_amount');
        $commission = (float) (clone $financialQuery)->sum('commission_amount');

        return [
            'entries' => (clone $query)->count(),
            'customers' => (clone $query)->distinct('contact_id')->count('contact_id'),
            'gross' => $gross,
            'commission' => $commission,
            'net' => (float) (clone $financialQuery)->sum('net_amount'),
            'profit' => (float) (clone $financialQuery)->sum('profit_amount'),
            'negative' => (clone $financialQuery)->where('profit_amount', '<', 0)->count(),
            'average_commission_rate' => $gross > 0 ? round(($commission / $gross) * 100, 2) : 0,
        ];
    }

    protected function emptyStats(): array
    {
        return [
            'entries' => 0,
            'customers' => 0,
            'gross' => 0,
            'commission' => 0,
            'net' => 0,
            'profit' => 0,
            'negative' => 0,
            'average_commission_rate' => 0,
        ];
    }

    protected function stores()
    {
        if (!Schema::hasTable('marketplace_stores')) {
            return collect();
        }

        return MarketplaceStore::query()
            ->where('user_id', auth()->id())
            ->orderBy('store_name')
            ->get(['id', 'store_name', 'marketplace']);
    }

    protected function contacts()
    {
        return CrmContact::query()
            ->where('user_id', auth()->id())
            ->latest('last_event_at')
            ->orderBy('display_name')
            ->limit(100)
            ->get(['id', 'display_name', 'primary_phone', 'primary_email']);
    }

    protected function recipes()
    {
        if (!Schema::hasTable('recipes')) {
            return collect();
        }

        $columns = ['id', 'name', 'version'];

        if (Schema::hasColumn('recipes', 'stock_code')) {
            $columns[] = 'stock_code';
        }

        return Recipe::query()
            ->where('user_id', auth()->id())
            ->active()
            ->orderBy('name')
            ->limit(120)
            ->get($columns);
    }

    protected function platforms()
    {
        $entryPlatforms = CrmCustomerLedgerEntry::query()
            ->where('user_id', auth()->id())
            ->whereNotNull('platform')
            ->distinct()
            ->orderBy('platform')
            ->pluck('platform');

        $storePlatforms = $this->stores()
            ->pluck('marketplace')
            ->filter();

        return $entryPlatforms
            ->merge($storePlatforms)
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    protected function selectedContact(): ?CrmContact
    {
        if (!$this->selectedContactId) {
            return null;
        }

        return CrmContact::query()
            ->where('user_id', auth()->id())
            ->whereKey($this->selectedContactId)
            ->first();
    }

    protected function selectedEntries()
    {
        if (!$this->selectedContactId) {
            return collect();
        }

        return CrmCustomerLedgerEntry::query()
            ->where('user_id', auth()->id())
            ->where('contact_id', $this->selectedContactId)
            ->with('store')
            ->latest('purchased_at')
            ->latest('id')
            ->limit(8)
            ->get();
    }

    protected function selectedContactStats(): array
    {
        if (!$this->selectedContactId) {
            return $this->emptyStats();
        }

        $query = CrmCustomerLedgerEntry::query()
            ->where('user_id', auth()->id())
            ->where('contact_id', $this->selectedContactId);
        $financialQuery = (clone $query)->whereNotIn('status', ['cancelled', 'returned']);
        $gross = (float) (clone $financialQuery)->sum('gross_amount');
        $commission = (float) (clone $financialQuery)->sum('commission_amount');

        return [
            'entries' => (clone $query)->count(),
            'customers' => 1,
            'gross' => $gross,
            'commission' => $commission,
            'net' => (float) (clone $financialQuery)->sum('net_amount'),
            'profit' => (float) (clone $financialQuery)->sum('profit_amount'),
            'negative' => (clone $financialQuery)->where('profit_amount', '<', 0)->count(),
            'average_commission_rate' => $gross > 0 ? round(($commission / $gross) * 100, 2) : 0,
        ];
    }

    protected function activeFilters(): array
    {
        $filters = [];

        if ($this->selectedContactId) {
            $contactName = $this->selectedContact()?->display_name;
            $filters[] = 'Müşteri: ' . ($contactName ?: '#' . $this->selectedContactId);
        }

        if ($this->search !== '') {
            $filters[] = 'Arama: ' . $this->search;
        }

        if ($this->platformFilter !== '') {
            $filters[] = 'Platform: ' . $this->platformFilter;
        }

        if ($this->storeFilter !== '') {
            $storeName = $this->stores()->firstWhere('id', (int) $this->storeFilter)?->store_name;
            $filters[] = 'Mağaza: ' . ($storeName ?: $this->storeFilter);
        }

        if ($this->statusFilter !== '') {
            $filters[] = 'Durum: ' . ($this->statusOptions()[$this->statusFilter] ?? $this->statusFilter);
        }

        if ($this->dateFilter !== '90') {
            $filters[] = 'Tarih: ' . ($this->dateOptions()[$this->dateFilter] ?? $this->dateFilter);
        }

        if ($this->marginFilter !== '') {
            $filters[] = 'Kârlılık: ' . ($this->marginOptions()[$this->marginFilter] ?? $this->marginFilter);
        }

        return $filters;
    }

    protected function rules(): array
    {
        $hasContact = filled($this->entryForm['contact_id'] ?? null);

        return [
            'entryForm.contact_id' => ['nullable', 'integer'],
            'entryForm.customer_name' => [$hasContact ? 'nullable' : 'required', 'string', 'max:180'],
            'entryForm.customer_phone' => ['nullable', 'string', 'max:40'],
            'entryForm.customer_email' => ['nullable', 'email', 'max:180'],
            'entryForm.store_id' => ['nullable', 'integer'],
            'entryForm.platform' => ['nullable', 'string', 'max:80'],
            'entryForm.marketplace_order_number' => ['nullable', 'string', 'max:120'],
            'entryForm.product_name' => ['nullable', 'string', 'max:255'],
            'entryForm.stock_code' => ['nullable', 'string', 'max:120'],
            'entryForm.barcode' => ['nullable', 'string', 'max:120'],
            'entryForm.recipe_id' => ['nullable', 'integer'],
            'entryForm.tariff_name' => ['nullable', 'string', 'max:120'],
            'entryForm.quantity' => ['required', 'numeric', 'min:0.01'],
            'entryForm.unit_price' => ['required', 'numeric', 'min:0'],
            'entryForm.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'entryForm.commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'entryForm.commission_amount' => ['nullable', 'numeric', 'min:0'],
            'entryForm.cargo_amount' => ['nullable', 'numeric', 'min:0'],
            'entryForm.cost_amount' => ['nullable', 'numeric', 'min:0'],
            'entryForm.status' => ['required', 'string', 'max:40'],
            'entryForm.purchased_at' => ['nullable', 'date'],
            'entryForm.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'entryForm.customer_name' => 'müşteri adı',
            'entryForm.customer_email' => 'e-posta',
            'entryForm.product_name' => 'ürün',
            'entryForm.quantity' => 'adet',
            'entryForm.unit_price' => 'birim fiyat',
            'entryForm.commission_rate' => 'komisyon oranı',
            'entryForm.purchased_at' => 'satış tarihi',
        ];
    }

    protected function blankEntryForm(?int $contactId = null): array
    {
        return [
            'contact_id' => $contactId ? (string) $contactId : '',
            'customer_name' => '',
            'customer_phone' => '',
            'customer_email' => '',
            'store_id' => '',
            'platform' => '',
            'marketplace_order_number' => '',
            'product_name' => '',
            'stock_code' => '',
            'barcode' => '',
            'recipe_id' => '',
            'tariff_name' => '',
            'quantity' => 1,
            'unit_price' => 0,
            'discount_amount' => 0,
            'commission_rate' => 0,
            'commission_amount' => '',
            'cargo_amount' => 0,
            'cost_amount' => '',
            'status' => 'completed',
            'purchased_at' => now()->format('Y-m-d\TH:i'),
            'notes' => '',
        ];
    }

    protected function normalizeVisibleColumns(array $columns): array
    {
        $valid = array_keys(static::$allColumnDefs);
        $normalized = array_values(array_intersect($valid, $columns));

        return $normalized !== [] ? $normalized : $this->visibleColumns;
    }

    protected function ledgerTablesReady(): bool
    {
        return Schema::hasTable('crm_contacts')
            && Schema::hasTable('crm_customer_ledger_entries');
    }

    protected function contactBelongsToCurrentUser(int $contactId): bool
    {
        return CrmContact::query()
            ->where('user_id', auth()->id())
            ->whereKey($contactId)
            ->exists();
    }

    protected function notify(string $message, string $tone = 'success'): void
    {
        $this->ledgerMessage = $message;
        $this->ledgerMessageTone = $tone;
    }
}
