<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\JournalLine;
use App\Models\Payable;
use App\Models\Receivable;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\PartyLedgerEntry;
use App\Models\Party;
use App\Models\Warehouse;
use App\Models\MpProduct;
use InvalidArgumentException;

/**
 * Ticari ve Finansal Raporlama Servisi (Phase 10 Hardened).
 */
class ReportService
{
    /**
     * Filtre doğrulama ve yetki kontrolü.
     */
    protected function validateFilters(int $userId, array $filters = []): void
    {
        if (isset($filters['legal_entity_id']) && $filters['legal_entity_id'] !== null && $filters['legal_entity_id'] !== '') {
            $le = \App\Models\LegalEntity::where('user_id', $userId)->find($filters['legal_entity_id']);
            if (!$le) {
                throw new InvalidArgumentException('Belirtilen yasal birlik bulunamadı veya bu kullanıcıya ait değil.');
            }
            if (!$le->is_active) {
                throw new InvalidArgumentException('Seçilen yasal birlik aktif değil.');
            }
        }

        if (isset($filters['party_id']) && $filters['party_id'] !== null && $filters['party_id'] !== '') {
            $party = Party::where('user_id', $userId)->find($filters['party_id']);
            if (!$party) {
                throw new InvalidArgumentException('Belirtilen cari bulunamadı veya bu kullanıcıya ait değil.');
            }
        }

        if (isset($filters['warehouse_id']) && $filters['warehouse_id'] !== null && $filters['warehouse_id'] !== '') {
            $wh = Warehouse::where('user_id', $userId)->find($filters['warehouse_id']);
            if (!$wh) {
                throw new InvalidArgumentException('Belirtilen depo bulunamadı veya bu kullanıcıya ait değil.');
            }
            if (!$wh->is_active) {
                throw new InvalidArgumentException('Seçilen depo aktif değil.');
            }
        }
    }

    /**
     * Alacak Yaşlandırma Raporu (Aged Receivables).
     */
    public function receivablesAging(int $userId, array $filters = []): array
    {
        $this->validateFilters($userId, $filters);

        $query = Receivable::where('user_id', $userId)
            ->whereNotIn('status', ['paid', 'voided']);

        if (isset($filters['legal_entity_id']) && $filters['legal_entity_id'] !== null && $filters['legal_entity_id'] !== '') {
            $query->where('legal_entity_id', $filters['legal_entity_id']);
        }
        if (isset($filters['party_id']) && $filters['party_id'] !== null && $filters['party_id'] !== '') {
            $query->where('party_id', $filters['party_id']);
        }
        if (isset($filters['date_from']) && !empty($filters['date_from'])) {
            $query->whereDate('document_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to']) && !empty($filters['date_to'])) {
            $query->whereDate('document_date', '<=', $filters['date_to']);
        }

        $receivables = $query->get();

        $summary = [
            'current'      => 0.0,
            'days_1_30'    => 0.0,
            'days_31_60'   => 0.0,
            'days_61_90'   => 0.0,
            'days_90_plus' => 0.0,
            'total_open'   => 0.0,
            'count'        => 0,
        ];

        $today = now()->startOfDay();

        foreach ($receivables as $item) {
            $remaining = (float) $item->remainingAmount();
            if ($remaining <= 0.005) {
                continue;
            }

            $summary['total_open'] += $remaining;
            $summary['count']++;

            $dueDate = $item->due_date ? \Carbon\Carbon::parse($item->due_date)->startOfDay() : null;

            if (!$dueDate || $dueDate->greaterThanOrEqualTo($today)) {
                $summary['current'] += $remaining;
                continue;
            }

            $diffDays = $today->diffInDays($dueDate, true);

            if ($diffDays <= 30) {
                $summary['days_1_30'] += $remaining;
            } elseif ($diffDays <= 60) {
                $summary['days_31_60'] += $remaining;
            } elseif ($diffDays <= 90) {
                $summary['days_61_90'] += $remaining;
            } else {
                $summary['days_90_plus'] += $remaining;
            }
        }

        return $summary;
    }

    /**
     * Borç Yaşlandırma Raporu (Aged Payables).
     */
    public function payablesAging(int $userId, array $filters = []): array
    {
        $this->validateFilters($userId, $filters);

        $query = Payable::where('user_id', $userId)
            ->whereNotIn('status', ['paid', 'voided']);

        if (isset($filters['legal_entity_id']) && $filters['legal_entity_id'] !== null && $filters['legal_entity_id'] !== '') {
            $query->where('legal_entity_id', $filters['legal_entity_id']);
        }
        if (isset($filters['party_id']) && $filters['party_id'] !== null && $filters['party_id'] !== '') {
            $query->where('party_id', $filters['party_id']);
        }
        if (isset($filters['date_from']) && !empty($filters['date_from'])) {
            $query->whereDate('document_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to']) && !empty($filters['date_to'])) {
            $query->whereDate('document_date', '<=', $filters['date_to']);
        }

        $payables = $query->get();

        $summary = [
            'current'      => 0.0,
            'days_1_30'    => 0.0,
            'days_31_60'   => 0.0,
            'days_61_90'   => 0.0,
            'days_90_plus' => 0.0,
            'total_open'   => 0.0,
            'count'        => 0,
        ];

        $today = now()->startOfDay();

        foreach ($payables as $item) {
            $remaining = (float) $item->remainingAmount();
            if ($remaining <= 0.005) {
                continue;
            }

            $summary['total_open'] += $remaining;
            $summary['count']++;

            $dueDate = $item->due_date ? \Carbon\Carbon::parse($item->due_date)->startOfDay() : null;

            if (!$dueDate || $dueDate->greaterThanOrEqualTo($today)) {
                $summary['current'] += $remaining;
                continue;
            }

            $diffDays = $today->diffInDays($dueDate, true);

            if ($diffDays <= 30) {
                $summary['days_1_30'] += $remaining;
            } elseif ($diffDays <= 60) {
                $summary['days_31_60'] += $remaining;
            } elseif ($diffDays <= 90) {
                $summary['days_61_90'] += $remaining;
            } else {
                $summary['days_90_plus'] += $remaining;
            }
        }

        return $summary;
    }

    /**
     * 30 günlük nakit akış tahmini (Daily cash flow forecast).
     */
    public function cashFlowForecast(int $userId, int $days = 30, array $filters = []): array
    {
        $this->validateFilters($userId, $filters);

        // 1. Başlangıç Nakit/Banka bakiyesi
        $cashQuery = Account::where('user_id', $userId)
            ->where(fn($q) => $q->where('is_cash_account', true)->orWhere('is_bank_account', true))
            ->where('is_active', true);

        if (isset($filters['legal_entity_id']) && $filters['legal_entity_id'] !== null && $filters['legal_entity_id'] !== '') {
            $cashQuery->where('legal_entity_id', $filters['legal_entity_id']);
        }

        $cashAccounts = $cashQuery->get();
        $openingBalance = 0.0;
        foreach ($cashAccounts as $acc) {
            $openingBalance += (float) $acc->balance();
        }

        $today = now()->startOfDay();
        $endDateStr = $today->copy()->addDays($days)->toDateString();

        // 2. Açık Alacakları Çek (Receivable)
        $recQuery = Receivable::where('user_id', $userId)
            ->whereNotIn('status', ['paid', 'voided']);

        if (isset($filters['legal_entity_id']) && $filters['legal_entity_id'] !== null && $filters['legal_entity_id'] !== '') {
            $recQuery->where('legal_entity_id', $filters['legal_entity_id']);
        }
        if (isset($filters['party_id']) && $filters['party_id'] !== null && $filters['party_id'] !== '') {
            $recQuery->where('party_id', $filters['party_id']);
        }
        if (isset($filters['date_from']) && !empty($filters['date_from'])) {
            $recQuery->whereDate('document_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to']) && !empty($filters['date_to'])) {
            $recQuery->whereDate('document_date', '<=', $filters['date_to']);
        }

        $receivables = $recQuery->get();

        // 3. Açık Borçları Çek (Payable)
        $payQuery = Payable::where('user_id', $userId)
            ->whereNotIn('status', ['paid', 'voided']);

        if (isset($filters['legal_entity_id']) && $filters['legal_entity_id'] !== null && $filters['legal_entity_id'] !== '') {
            $payQuery->where('legal_entity_id', $filters['legal_entity_id']);
        }
        if (isset($filters['party_id']) && $filters['party_id'] !== null && $filters['party_id'] !== '') {
            $payQuery->where('party_id', $filters['party_id']);
        }
        if (isset($filters['date_from']) && !empty($filters['date_from'])) {
            $payQuery->whereDate('document_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to']) && !empty($filters['date_to'])) {
            $payQuery->whereDate('document_date', '<=', $filters['date_to']);
        }

        $payables = $payQuery->get();

        // Gün bazlı matris hazırlama (days = 30 ise: bugün + 30 gün = 31 satır)
        $dailyData = [];
        for ($i = 0; $i <= $days; $i++) {
            $dateStr = $today->copy()->addDays($i)->toDateString();
            $dailyData[$dateStr] = [
                'expected_inflow'  => 0.0,
                'expected_outflow' => 0.0,
            ];
        }

        $totalInflow = 0.0;
        $totalOutflow = 0.0;

        // Alacakları yerleştir
        foreach ($receivables as $r) {
            $remaining = (float) $r->remainingAmount();
            if ($remaining <= 0.005) {
                continue;
            }

            $dueDate = $r->due_date ? \Carbon\Carbon::parse($r->due_date)->toDateString() : $today->toDateString();

            // Sadece horizon içindekileri ve geçmiştekileri topla (gelecekteki sınır dışındakileri dahil etme)
            if ($dueDate > $endDateStr) {
                continue;
            }

            $totalInflow += $remaining;

            if ($dueDate < $today->toDateString()) {
                // Vadesi geçmiş alacakları ilk güne yansıtıyoruz
                $dailyData[$today->toDateString()]['expected_inflow'] += $remaining;
            } else {
                $dailyData[$dueDate]['expected_inflow'] += $remaining;
            }
        }

        // Borçları yerleştir
        foreach ($payables as $p) {
            $remaining = (float) $p->remainingAmount();
            if ($remaining <= 0.005) {
                continue;
            }

            $dueDate = $p->due_date ? \Carbon\Carbon::parse($p->due_date)->toDateString() : $today->toDateString();

            // Sadece horizon içindekileri ve geçmiştekileri topla (gelecekteki sınır dışındakileri dahil etme)
            if ($dueDate > $endDateStr) {
                continue;
            }

            $totalOutflow += $remaining;

            if ($dueDate < $today->toDateString()) {
                // Vadesi geçmiş borçları ilk güne yansıtıyoruz
                $dailyData[$today->toDateString()]['expected_outflow'] += $remaining;
            } else {
                $dailyData[$dueDate]['expected_outflow'] += $remaining;
            }
        }

        // Kümülatif tahmin hesaplama
        $dailyRows = [];
        $runningBalance = $openingBalance;

        foreach ($dailyData as $date => $values) {
            $inflow = $values['expected_inflow'];
            $outflow = $values['expected_outflow'];
            $net = $inflow - $outflow;
            $runningBalance += $net;

            $dailyRows[] = [
                'date'              => $date,
                'expected_inflow'   => $inflow,
                'expected_outflow'  => $outflow,
                'net_flow'          => $net,
                'projected_balance' => $runningBalance,
            ];
        }

        return [
            'opening_cash_balance'      => $openingBalance,
            'total_expected_inflows'    => $totalInflow,
            'total_expected_outflows'   => $totalOutflow,
            'projected_closing_balance' => $runningBalance,
            'daily_rows'                => $dailyRows,
        ];
    }

    /**
     * Gelir / Gider özeti.
     */
    public function incomeExpenseSummary(int $userId, array $filters = []): array
    {
        $this->validateFilters($userId, $filters);

        $query = JournalLine::where('user_id', $userId)
            ->whereHas('journalEntry', function($q) use ($filters) {
                $q->where('status', 'posted');

                if (isset($filters['date_from']) && !empty($filters['date_from'])) {
                    $q->whereDate('entry_date', '>=', $filters['date_from']);
                }
                if (isset($filters['date_to']) && !empty($filters['date_to'])) {
                    $q->whereDate('entry_date', '<=', $filters['date_to']);
                }
                if (isset($filters['legal_entity_id']) && $filters['legal_entity_id'] !== null && $filters['legal_entity_id'] !== '') {
                    $q->where('legal_entity_id', $filters['legal_entity_id']);
                }
            })
            ->whereHas('account', function($q) {
                $q->whereIn('type', ['revenue', 'expense']);
            })
            ->with(['account']);

        $lines = $query->get();

        $rows = [];
        $totalIncome = 0.0;
        $totalExpense = 0.0;

        // Hesap bazlı gruplama
        $grouped = $lines->groupBy('account_id');

        foreach ($grouped as $accountId => $accLines) {
            $account = $accLines->first()->account;
            $type = $account->type;

            $debitSum = (float) $accLines->sum('debit_base_amount');
            $creditSum = (float) $accLines->sum('credit_base_amount');

            if ($type === 'revenue') {
                $amount = $creditSum - $debitSum;
                $totalIncome += $amount;
            } else {
                $amount = $debitSum - $creditSum;
                $totalExpense += $amount;
            }

            if (abs($amount) > 0.005) {
                $rows[] = [
                    'account_code' => $account->code,
                    'account_name' => $account->name,
                    'type'         => $type,
                    'amount'       => $amount,
                ];
            }
        }

        // Hesap koduna göre sırala
        usort($rows, fn($a, $b) => strcmp($a['account_code'], $b['account_code']));

        return [
            'total_income'  => $totalIncome,
            'total_expense' => $totalExpense,
            'net_result'    => $totalIncome - $totalExpense,
            'rows'          => $rows,
        ];
    }

    /**
     * Stok envanter değeri raporu.
     */
    public function stockInventoryValue(int $userId, array $filters = []): array
    {
        $this->validateFilters($userId, $filters);

        $warehouseQuery = Warehouse::where('user_id', $userId)->where('is_active', true);

        if (isset($filters['legal_entity_id']) && $filters['legal_entity_id'] !== null && $filters['legal_entity_id'] !== '') {
            $warehouseQuery->where('legal_entity_id', $filters['legal_entity_id']);
        }

        if (isset($filters['warehouse_id']) && $filters['warehouse_id'] !== null && $filters['warehouse_id'] !== '') {
            $wh = Warehouse::where('user_id', $userId)->find($filters['warehouse_id']);
            if ($wh && isset($filters['legal_entity_id']) && $filters['legal_entity_id'] !== null && $filters['legal_entity_id'] !== '' && (int)$wh->legal_entity_id !== (int)$filters['legal_entity_id']) {
                throw new InvalidArgumentException('Seçilen depo belirtilen yasal birliğe ait değil.');
            }
            $warehouseQuery->where('id', $filters['warehouse_id']);
        }

        $warehouseIds = $warehouseQuery->pluck('id')->toArray();

        $query = StockBalance::where('user_id', $userId)
            ->whereIn('warehouse_id', $warehouseIds);

        $balances = $query->get();

        $totalQty = 0;
        $totalValue = 0.0;
        $lowStockCount = 0;
        $outOfStockCount = 0;
        $rows = [];

        // Ürün listesini çekelim
        $mpProducts = MpProduct::where('user_id', $userId)->get()->keyBy('stock_code');
        $warehouses = Warehouse::where('user_id', $userId)->get()->keyBy('id');

        foreach ($balances as $b) {
            $qty = (int) $b->quantity;
            $totalQty += $qty;

            $prod = $mpProducts->get($b->stock_code);
            $productName = $prod ? $prod->product_name : 'Bilinmeyen Ürün (' . $b->stock_code . ')';
            $unitCost = $prod ? (float) $prod->cogs : 0.0;

            $wh = $warehouses->get($b->warehouse_id);

            // Eğer stock movement içinde bu stock_code için daha güncel bir in movement maliyeti varsa onu alalım
            $lastMovement = StockMovement::where('user_id', $userId)
                ->where('stock_code', $b->stock_code)
                ->where('direction', 'in')
                ->where('status', 'posted')
                ->whereNotNull('unit_cost')
                ->where('warehouse_id', $b->warehouse_id)
                ->when($wh && $wh->legal_entity_id, fn($q) => $q->where('legal_entity_id', $wh->legal_entity_id))
                ->orderByDesc('id')
                ->first();

            if ($lastMovement) {
                $unitCost = (float) $lastMovement->unit_cost;
            }

            $invValue = $qty * $unitCost;
            $totalValue += $invValue;

            $threshold = $prod ? (int) $prod->critical_stock_threshold : 5;

            $status = 'in_stock';
            if ($qty <= 0) {
                $status = 'out_of_stock';
                $outOfStockCount++;
            } elseif ($qty <= $threshold) {
                $status = 'critical';
                $lowStockCount++;
            }

            $whName = $wh ? $wh->name : 'Bilinmeyen Depo';

            $rows[] = [
                'product_id'      => $b->product_id,
                'product_name'    => $productName,
                'stock_code'      => $b->stock_code,
                'warehouse_id'    => $b->warehouse_id,
                'warehouse_name'  => $whName,
                'quantity'        => $qty,
                'unit_cost'       => $unitCost,
                'inventory_value' => $invValue,
                'status'          => $status,
            ];
        }

        // Tüketilmiş (Out of Stock) ama balances tablosunda hiç kaydı bulunmayan mp_products var mı kontrol et
        // Sadece warehouse_id ve legal_entity_id filtresi YOKSA bu tespiti yapabiliriz
        if ((!isset($filters['warehouse_id']) || empty($filters['warehouse_id'])) && (!isset($filters['legal_entity_id']) || empty($filters['legal_entity_id']))) {
            $recordedStockCodes = $balances->pluck('stock_code')->toArray();
            foreach ($mpProducts as $code => $prod) {
                if (!in_array($code, $recordedStockCodes, true)) {
                    $outOfStockCount++;
                }
            }
        }

        return [
            'total_quantity'        => $totalQty,
            'total_inventory_value' => $totalValue,
            'low_stock_count'       => $lowStockCount,
            'out_of_stock_count'    => $outOfStockCount,
            'rows'                  => $rows,
        ];
    }

    /**
     * Cari bakiye özeti (Party ledger entries summary).
     */
    public function partyBalanceSummary(int $userId, array $filters = []): array
    {
        $this->validateFilters($userId, $filters);

        $query = PartyLedgerEntry::where('user_id', $userId)
            ->where('status', 'posted');

        if (isset($filters['legal_entity_id']) && $filters['legal_entity_id'] !== null && $filters['legal_entity_id'] !== '') {
            $query->where('legal_entity_id', $filters['legal_entity_id']);
        }
        if (isset($filters['party_id']) && $filters['party_id'] !== null && $filters['party_id'] !== '') {
            $query->where('party_id', $filters['party_id']);
        }
        if (isset($filters['date_from']) && !empty($filters['date_from'])) {
            $query->whereDate('document_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to']) && !empty($filters['date_to'])) {
            $query->whereDate('document_date', '<=', $filters['date_to']);
        }

        $entries = $query->get();

        $partyBalances = [];
        $parties = Party::where('user_id', $userId)->get()->keyBy('id');

        foreach ($entries as $e) {
            $pId = $e->party_id;
            if (!isset($partyBalances[$pId])) {
                $partyBalances[$pId] = 0.0;
            }

            // debit - credit
            $partyBalances[$pId] += ((float) $e->debit_base_amount - (float) $e->credit_base_amount);
        }

        $totalReceivable = 0.0;
        $totalPayable = 0.0;
        $topDebtors = [];
        $topCreditors = [];

        foreach ($partyBalances as $pId => $bal) {
            $party = $parties->get($pId);
            $partyName = $party ? $party->display_name : 'Bilinmeyen Cari';

            if ($bal > 0.005) {
                $totalReceivable += $bal;
                $topDebtors[] = [
                    'party_id'   => $pId,
                    'party_name' => $partyName,
                    'balance'    => $bal,
                ];
            } elseif ($bal < -0.005) {
                $absBal = abs($bal);
                $totalPayable += $absBal;
                $topCreditors[] = [
                    'party_id'   => $pId,
                    'party_name' => $partyName,
                    'balance'    => $bal,
                ];
            }
        }

        // Sıralamalar
        usort($topDebtors, fn($a, $b) => $b['balance'] <=> $a['balance']);
        usort($topCreditors, fn($a, $b) => abs($b['balance']) <=> abs($a['balance']));

        return [
            'total_receivable_balance' => $totalReceivable,
            'total_payable_balance'    => $totalPayable,
            'net_balance'              => $totalReceivable - $totalPayable,
            'active_party_count'       => count($partyBalances),
            'top_debtors'              => array_slice($topDebtors, 0, 5),
            'top_creditors'            => array_slice($topCreditors, 0, 5),
        ];
    }

    /**
     * Yönetim özeti (Executive Summary).
     */
    public function executiveSummary(int $userId, array $filters = []): array
    {
        $this->validateFilters($userId, $filters);

        $receivables = $this->receivablesAging($userId, $filters);
        $payables = $this->payablesAging($userId, $filters);
        $cashFlow = $this->cashFlowForecast($userId, 30, $filters);
        $stock = $this->stockInventoryValue($userId, $filters);
        $parties = $this->partyBalanceSummary($userId, $filters);

        // Gelir-Gider filtrelerine tarih aralığı ekleyelim (Bu ayın başlangıcından bugüne varsayılan)
        $ieFilters = $filters;
        if (!isset($ieFilters['date_from'])) {
            $ieFilters['date_from'] = now()->startOfMonth()->toDateString();
        }
        if (!isset($ieFilters['date_to'])) {
            $ieFilters['date_to'] = now()->toDateString();
        }
        $incomeExpense = $this->incomeExpenseSummary($userId, $ieFilters);

        return [
            'total_open_receivables' => $receivables['total_open'],
            'total_open_payables'    => $payables['total_open'],
            'cash_balance'           => $cashFlow['opening_cash_balance'],
            'projected_closing_cash' => $cashFlow['projected_closing_balance'],
            'inventory_value'        => $stock['total_inventory_value'],
            'net_profit_loss'        => $incomeExpense['net_result'],
            'active_parties'         => $parties['active_party_count'],
        ];
    }

    // ─── Legacy Metotlar (Geriye Dönük Uyumluluk) ──────────────────────────

    public function getAgedReceivables(int $userId): array
    {
        $aging = $this->receivablesAging($userId);
        return [
            'not_due'      => $aging['current'],
            'aged_0_30'    => $aging['days_1_30'],
            'aged_31_60'   => $aging['days_31_60'],
            'aged_61_90'   => $aging['days_61_90'],
            'aged_90_plus' => $aging['days_90_plus'],
            'total'        => $aging['total_open'],
        ];
    }

    public function getAgedPayables(int $userId): array
    {
        $aging = $this->payablesAging($userId);
        return [
            'not_due'      => $aging['current'],
            'aged_0_30'    => $aging['days_1_30'],
            'aged_31_60'   => $aging['days_31_60'],
            'aged_61_90'   => $aging['days_61_90'],
            'aged_90_plus' => $aging['days_90_plus'],
            'total'        => $aging['total_open'],
        ];
    }

    public function getProfitLossSummary(int $userId, string $dateFrom, string $dateTo): array
    {
        $summary = $this->incomeExpenseSummary($userId, ['date_from' => $dateFrom, 'date_to' => $dateTo]);
        return [
            'gross_revenue' => $summary['total_income'],
            'total_expense' => $summary['total_expense'],
            'net_profit'    => $summary['net_result'],
        ];
    }

    public function getWarehouseStockValue(int $userId, ?int $warehouseId = null): array
    {
        $val = $this->stockInventoryValue($userId, ['warehouse_id' => $warehouseId]);
        return [
            'total_value' => $val['total_inventory_value'],
            'total_items' => $val['total_quantity'],
        ];
    }

    public function getTrialBalance(int $userId, string $dateFrom, string $dateTo): array
    {
        $accounts = Account::where('user_id', $userId)->orderBy('code')->get();
        $trialBalance = [];

        foreach ($accounts as $account) {
            $debit = (float) JournalLine::where('account_id', $account->id)
                ->whereHas('journalEntry', function($q) use ($dateFrom, $dateTo) {
                    $q->where('status', 'posted')
                      ->whereBetween('entry_date', [$dateFrom, $dateTo]);
                })->sum('debit_base_amount');

            $credit = (float) JournalLine::where('account_id', $account->id)
                ->whereHas('journalEntry', function($q) use ($dateFrom, $dateTo) {
                    $q->where('status', 'posted')
                      ->whereBetween('entry_date', [$dateFrom, $dateTo]);
                })->sum('credit_base_amount');

            $debitBalance = 0.0;
            $creditBalance = 0.0;

            if ($debit > $credit) {
                $debitBalance = $debit - $credit;
            } elseif ($credit > $debit) {
                $creditBalance = $credit - $debit;
            }

            $trialBalance[] = [
                'code' => $account->code,
                'name' => $account->name,
                'debit' => $debit,
                'credit' => $credit,
                'debit_balance' => $debitBalance,
                'credit_balance' => $creditBalance,
            ];
        }

        return $trialBalance;
    }

    public function getBalanceSheet(int $userId, string $dateTo): array
    {
        $accounts = Account::where('user_id', $userId)->get();

        $assets = [];
        $liabilitiesAndEquity = [];

        $totalAssets = 0.0;
        $totalLiabilitiesAndEquity = 0.0;

        foreach ($accounts as $account) {
            $debit = (float) JournalLine::where('account_id', $account->id)
                ->whereHas('journalEntry', function($q) use ($dateTo) {
                    $q->where('status', 'posted')
                      ->where('entry_date', '<=', $dateTo);
                })->sum('debit_base_amount');

            $credit = (float) JournalLine::where('account_id', $account->id)
                ->whereHas('journalEntry', function($q) use ($dateTo) {
                    $q->where('status', 'posted')
                      ->where('entry_date', '<=', $dateTo);
                })->sum('credit_base_amount');

            $bal = 0.0;
            if ($account->type === 'asset' || $account->type === 'expense') {
                $bal = $debit - $credit;
            } else {
                $bal = $credit - $debit;
            }

            if ($bal === 0.0) {
                continue;
            }

            $firstChar = substr($account->code, 0, 1);
            if ($firstChar === '1' || $firstChar === '2') {
                $assets[] = [
                    'code' => $account->code,
                    'name' => $account->name,
                    'balance' => $bal,
                ];
                $totalAssets += $bal;
            } elseif ($firstChar === '3' || $firstChar === '4' || $firstChar === '5') {
                $liabilitiesAndEquity[] = [
                    'code' => $account->code,
                    'name' => $account->name,
                    'balance' => $bal,
                ];
                $totalLiabilitiesAndEquity += $bal;
            }
        }

        // Net profit/loss up to dateTo (starts with '6' and '7')
        $revDebit = (float) JournalLine::whereHas('account', function($q) use ($userId) {
                $q->where('user_id', $userId)->where('type', 'revenue');
            })->whereHas('journalEntry', function($q) use ($dateTo) {
                $q->where('status', 'posted')->where('entry_date', '<=', $dateTo);
            })->sum('debit_base_amount');

        $revCredit = (float) JournalLine::whereHas('account', function($q) use ($userId) {
                $q->where('user_id', $userId)->where('type', 'revenue');
            })->whereHas('journalEntry', function($q) use ($dateTo) {
                $q->where('status', 'posted')->where('entry_date', '<=', $dateTo);
            })->sum('credit_base_amount');

        $expDebit = (float) JournalLine::whereHas('account', function($q) use ($userId) {
                $q->where('user_id', $userId)->where('type', 'expense');
            })->whereHas('journalEntry', function($q) use ($dateTo) {
                $q->where('status', 'posted')->where('entry_date', '<=', $dateTo);
            })->sum('debit_base_amount');

        $expCredit = (float) JournalLine::whereHas('account', function($q) use ($userId) {
                $q->where('user_id', $userId)->where('type', 'expense');
            })->whereHas('journalEntry', function($q) use ($dateTo) {
                $q->where('status', 'posted')->where('entry_date', '<=', $dateTo);
            })->sum('credit_base_amount');

        $revenue = $revCredit - $revDebit;
        $expense = $expDebit - $expCredit;
        $netProfit = $revenue - $expense;

        if ($netProfit !== 0.0) {
            $liabilitiesAndEquity[] = [
                'code' => '590/591',
                'name' => 'Dönem Net Kârı veya Zararı',
                'balance' => $netProfit,
            ];
            $totalLiabilitiesAndEquity += $netProfit;
        }

        return [
            'assets' => $assets,
            'total_assets' => $totalAssets,
            'liabilities_and_equity' => $liabilitiesAndEquity,
            'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,
        ];
    }
}
