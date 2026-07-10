<?php

namespace App\Livewire\Accounting;

use App\Models\Party;
use App\Models\Warehouse;
use App\Models\LegalEntity;
use App\Services\Accounting\ReportService;
use Livewire\Component;
use InvalidArgumentException;

/**
 * Yönetim Raporları Livewire Bileşeni (Phase 10 Hardened).
 */
class Reports extends Component
{
    // Rapor Seçimi
    public string $reportType = 'executive'; // executive, receivables_aging, payables_aging, cash_flow, income_expense, stock_inventory, party_balances, trial_balance, balance_sheet

    // Filtreler
    public string $dateFrom = '';
    public string $dateTo = '';
    public ?string $legalEntityId = '';
    public ?string $partyId = '';
    public ?string $warehouseId = '';

    // UI & Tablo Aletleri
    public string $sortColumn = 'id';
    public string $sortDirection = 'desc';
    public array $visibleColumns = [];

    // Durum Mesajları
    public string $message = '';
    public string $messageType = 'success';

    // Whitelist sıralanabilir kolonlar
    public static array $sortableColumns = [
        'id', 'date', 'account_code', 'account_name', 'type', 'amount', 'stock_code', 'product_name', 'quantity', 'unit_cost', 'inventory_value', 'status', 'party_name', 'balance'
    ];

    public function mount(): void
    {
        $this->dateFrom = now()->startOfYear()->toDateString();
        $this->dateTo = now()->toDateString();

        $this->resetVisibleColumns();
    }

    /**
     * Rapor türüne göre varsayılan görünür kolonları belirler.
     */
    public function resetVisibleColumns(): void
    {
        $this->visibleColumns = match ($this->reportType) {
            'receivables_aging', 'payables_aging' => ['current', 'days_1_30', 'days_31_60', 'days_61_90', 'days_90_plus', 'total_open', 'count'],
            'cash_flow' => ['date', 'expected_inflow', 'expected_outflow', 'net_flow', 'projected_balance'],
            'income_expense' => ['account_code', 'account_name', 'type', 'amount'],
            'stock_inventory' => ['product_name', 'stock_code', 'warehouse_name', 'quantity', 'unit_cost', 'inventory_value', 'status'],
            'party_balances' => ['party_name', 'balance'],
            'trial_balance' => ['code', 'name', 'debit', 'credit', 'debit_balance', 'credit_balance'],
            'balance_sheet' => ['code', 'name', 'balance'],
            default => []
        };
    }

    public function updatedReportType(): void
    {
        $this->message = '';
        $this->resetVisibleColumns();
    }

    public function updated(string $propertyName): void
    {
        if (in_array($propertyName, ['legalEntityId', 'partyId', 'warehouseId'], true)) {
            $this->message = '';
            try {
                $userId = auth()->id();
                $service = app(ReportService::class);
                // Validate filters using one of the service methods
                $service->receivablesAging($userId, $this->getFilterPayload());
            } catch (InvalidArgumentException $e) {
                $this->message = 'Güvenlik Uyarısı: ' . $e->getMessage();
                $this->messageType = 'error';
            }
        }
    }

    public function clearFilters(): void
    {
        $this->dateFrom = now()->startOfYear()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->legalEntityId = '';
        $this->partyId = '';
        $this->warehouseId = '';
        $this->message = '';
    }

    public function refreshReport(): void
    {
        $this->message = '';
    }

    /**
     * Kolonları sıralar. Whitelist korumalıdır.
     */
    public function sortTable(string $column): void
    {
        if (!in_array($column, self::$sortableColumns, true)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn    = $column;
            $this->sortDirection = 'asc';
        }
    }

    /**
     * Kolon gizleme/gösterme.
     */
    public function toggleColumn(string $column): void
    {
        if (in_array($column, $this->visibleColumns, true)) {
            $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$column]));
        } else {
            $this->visibleColumns[] = $column;
        }
    }

    /**
     * Kolon başlık tanımları.
     */
    public function getColumnDefsProperty(): array
    {
        return match ($this->reportType) {
            'receivables_aging', 'payables_aging' => [
                'current'      => 'Vadesi Gelmemiş',
                'days_1_30'    => '1-30 Gün Geciken',
                'days_31_60'   => '31-60 Gün Geciken',
                'days_61_90'   => '61-90 Gün Geciken',
                'days_90_plus' => '90+ Gün Geciken',
                'total_open'   => 'Toplam Açık Bakiye',
                'count'        => 'Fatura Adedi',
            ],
            'cash_flow' => [
                'date'             => 'Tarih',
                'expected_inflow'  => 'Beklenen Giriş (Tahsilat)',
                'expected_outflow' => 'Beklenen Çıkış (Ödeme)',
                'net_flow'         => 'Net Akış',
                'projected_balance'=> 'Öngörülen Kasa Bakiyesi',
            ],
            'income_expense' => [
                'account_code' => 'Hesap Kodu',
                'account_name' => 'Hesap Adı',
                'type'         => 'Tip',
                'amount'       => 'Tutar (TRY)',
            ],
            'stock_inventory' => [
                'product_name'    => 'Ürün Adı',
                'stock_code'      => 'Stok Kodu',
                'warehouse_name'  => 'Depo',
                'quantity'        => 'Mevcut Stok',
                'unit_cost'       => 'Birim Maliyet',
                'inventory_value' => 'Envanter Değeri',
                'status'          => 'Stok Durumu',
            ],
            'party_balances' => [
                'party_name' => 'Müşteri / Tedarikçi',
                'balance'    => 'Bakiye (TRY)',
            ],
            'trial_balance' => [
                'code'           => 'Hesap Kodu',
                'name'           => 'Hesap Adı',
                'debit'          => 'Borç Toplamı',
                'credit'         => 'Alacak Toplamı',
                'debit_balance'  => 'Borç Bakiyesi',
                'credit_balance' => 'Alacak Bakiyesi',
            ],
            'balance_sheet' => [
                'code'    => 'Hesap Kodu',
                'name'    => 'Hesap Adı',
                'balance' => 'Bakiye (TRY)',
            ],
            default => []
        };
    }

    /**
     * Rapor filtresi payload'ını hazırlar.
     */
    protected function getFilterPayload(): array
    {
        return [
            'date_from'       => $this->dateFrom ?: null,
            'date_to'         => $this->dateTo ?: null,
            'legal_entity_id' => $this->legalEntityId ? (int) $this->legalEntityId : null,
            'party_id'        => $this->partyId ? (int) $this->partyId : null,
            'warehouse_id'    => $this->warehouseId ? (int) $this->warehouseId : null,
        ];
    }

    /**
     * Rapor verisini ReportService üzerinden çeker ve güvenli bir şekilde sıralar.
     */
    public function getReportDataProperty()
    {
        $userId = auth()->id();
        $service = app(ReportService::class);
        $filters = $this->getFilterPayload();

        try {
            $data = match ($this->reportType) {
                'receivables_aging' => $service->receivablesAging($userId, $filters),
                'payables_aging'    => $service->payablesAging($userId, $filters),
                'cash_flow'         => $service->cashFlowForecast($userId, 30, $filters),
                'income_expense'    => $service->incomeExpenseSummary($userId, $filters),
                'stock_inventory'   => $service->stockInventoryValue($userId, $filters),
                'party_balances'    => $service->partyBalanceSummary($userId, $filters),
                'trial_balance'     => $service->getTrialBalance($userId, $this->dateFrom, $this->dateTo),
                'balance_sheet'     => $service->getBalanceSheet($userId, $this->dateTo),
                default             => []
            };

            // Liste halindeki veriler için sıralama (Sorting)
            if (isset($data['rows']) && is_array($data['rows'])) {
                $col = $this->sortColumn;
                $dir = $this->sortDirection === 'asc' ? 1 : -1;

                usort($data['rows'], function ($a, $b) use ($col, $dir) {
                    $valA = $a[$col] ?? '';
                    $valB = $b[$col] ?? '';
                    if (is_numeric($valA) && is_numeric($valB)) {
                        return ($valA <=> $valB) * $dir;
                    }
                    return strcmp((string)$valA, (string)$valB) * $dir;
                });
            }

            return $data;

        } catch (InvalidArgumentException $e) {
            $this->message = 'Güvenlik Uyarısı: ' . $e->getMessage();
            $this->messageType = 'error';
            return [];
        } catch (\Exception $e) {
            $this->message = 'Hata: ' . $e->getMessage();
            $this->messageType = 'error';
            return [];
        }
    }

    /**
     * Yönetim Özeti (Executive Summary) KPI verileri.
     */
    public function getExecutiveSummaryProperty(): array
    {
        $userId = auth()->id();
        $service = app(ReportService::class);
        $filters = $this->getFilterPayload();

        try {
            return $service->executiveSummary($userId, $filters);
        } catch (InvalidArgumentException $e) {
            $this->message = 'Güvenlik Uyarısı: ' . $e->getMessage();
            $this->messageType = 'error';
            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    // Dropdown Seçenekleri (Sadece Auth Kullanıcıya Ait ve Aktif Kayıtlar)

    public function getLegalEntitiesProperty()
    {
        return LegalEntity::where('user_id', auth()->id())->active()->orderBy('name')->get();
    }

    public function getPartiesProperty()
    {
        return Party::where('user_id', auth()->id())->orderBy('display_name')->get();
    }

    public function getWarehousesProperty()
    {
        return Warehouse::where('user_id', auth()->id())->active()->orderBy('name')->get();
    }

    public function render()
    {
        return view('livewire.accounting.reports')
            ->layout('layouts.app');
    }
}
