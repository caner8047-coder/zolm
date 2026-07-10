<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\JournalLine;
use App\Models\Payable;
use App\Models\Receivable;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Ticari ve Finansal Raporlama Servisi (Phase 10).
 *
 * Sorumluluklar:
 * 1. Cari Bakiye ve Yaşlandırma Raporu (Aged Receivables & Payables).
 * 2. Nakit Akış Özeti (Cash Flow Statement).
 * 3. Gelir-Gider / Gelir Tablosu Raporu (Profit & Loss Summary).
 * 4. Stok Değer Raporu (Warehouse Stock Value).
 */
class ReportService
{
    /**
     * Alacak Yaşlandırma Raporu (Aged Receivables).
     * Faturaları vadesine kalan / geçen sürelere göre gruplar (0-30, 31-60, 61-90, 90+ gün).
     */
    public function getAgedReceivables(int $userId): array
    {
        $receivables = Receivable::where('user_id', $userId)
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'voided')
            ->get();

        $summary = [
            'not_due' => 0.0,
            'aged_0_30' => 0.0,
            'aged_31_60' => 0.0,
            'aged_61_90' => 0.0,
            'aged_90_plus' => 0.0,
            'total' => 0.0,
        ];

        $today = now()->startOfDay();

        foreach ($receivables as $item) {
            $amount = $item->remainingAmount();
            $summary['total'] += $amount;

            if (!$item->due_date || $item->due_date->greaterThanOrEqualTo($today)) {
                $summary['not_due'] += $amount;
                continue;
            }

            $diffDays = $today->diffInDays($item->due_date, true);

            if ($diffDays <= 30) {
                $summary['aged_0_30'] += $amount;
            } elseif ($diffDays <= 60) {
                $summary['aged_31_60'] += $amount;
            } elseif ($diffDays <= 90) {
                $summary['aged_61_90'] += $amount;
            } else {
                $summary['aged_90_plus'] += $amount;
            }
        }

        return $summary;
    }

    /**
     * Borç Yaşlandırma Raporu (Aged Payables).
     */
    public function getAgedPayables(int $userId): array
    {
        $payables = Payable::where('user_id', $userId)
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'voided')
            ->get();

        $summary = [
            'not_due' => 0.0,
            'aged_0_30' => 0.0,
            'aged_31_60' => 0.0,
            'aged_61_90' => 0.0,
            'aged_90_plus' => 0.0,
            'total' => 0.0,
        ];

        $today = now()->startOfDay();

        foreach ($payables as $item) {
            $amount = $item->remainingAmount();
            $summary['total'] += $amount;

            if (!$item->due_date || $item->due_date->greaterThanOrEqualTo($today)) {
                $summary['not_due'] += $amount;
                continue;
            }

            $diffDays = $today->diffInDays($item->due_date, true);

            if ($diffDays <= 30) {
                $summary['aged_0_30'] += $amount;
            } elseif ($diffDays <= 60) {
                $summary['aged_31_60'] += $amount;
            } elseif ($diffDays <= 90) {
                $summary['aged_61_90'] += $amount;
            } else {
                $summary['aged_90_plus'] += $amount;
            }
        }

        return $summary;
    }

    /**
     * Nakit Akış Raporu (Nakit/Banka mevcudu + vadesi gelen alacaklar - vadesi gelen borçlar).
     */
    public function getCashFlowForecast(int $userId): array
    {
        // 1. Nakit ve Banka Hesaplarının Toplamı
        $cashAccounts = Account::where('user_id', $userId)->where('is_cash_account', true)->get();
        $bankAccounts = Account::where('user_id', $userId)->where('is_bank_account', true)->get();

        $totalCash = 0.0;
        foreach ($cashAccounts as $acc) {
            $totalCash += $acc->balance();
        }

        $totalBank = 0.0;
        foreach ($bankAccounts as $acc) {
            $totalBank += $acc->balance();
        }

        // 2. Vadesi geçmiş / vadesi yaklaşan alacaklar (30 gün içinde tahsil edilebilecek)
        $agedReceivables = $this->getAgedReceivables($userId);
        $expectedInflow = $agedReceivables['total']; // Toplam açık alacaklar

        // 3. Vadesi geçmiş / vadesi yaklaşan borçlar (30 gün içinde ödenecek)
        $agedPayables = $this->getAgedPayables($userId);
        $expectedOutflow = $agedPayables['total']; // Toplam açık borçlar

        $forecast = ($totalCash + $totalBank) + $expectedInflow - $expectedOutflow;

        return [
            'cash_balance'     => $totalCash,
            'bank_balance'     => $totalBank,
            'total_liquidity'  => $totalCash + $totalBank,
            'expected_inflow'  => $expectedInflow,
            'expected_outflow' => $expectedOutflow,
            'net_forecast'     => $forecast,
        ];
    }

    /**
     * Gelir-Gider / Gelir Tablosu (P&L Summary).
     */
    public function getProfitLossSummary(int $userId, string $dateFrom, string $dateTo): array
    {
        // Gelirler (kod 600 - Yurt İçi Satışlar)
        $revenueLines = JournalLine::where('user_id', $userId)
            ->whereHas('journalEntry', function($q) use ($dateFrom, $dateTo) {
                $q->where('status', 'posted')
                  ->whereBetween('entry_date', [$dateFrom, $dateTo]);
            })
            ->whereHas('account', function($q) {
                $q->where('type', 'revenue');
            })
            ->get();

        $totalRevenue = (float) $revenueLines->sum('credit_base_amount') - (float) $revenueLines->sum('debit_base_amount');

        // Giderler (grup 760/770 vb.)
        $expenseLines = JournalLine::where('user_id', $userId)
            ->whereHas('journalEntry', function($q) use ($dateFrom, $dateTo) {
                $q->where('status', 'posted')
                  ->whereBetween('entry_date', [$dateFrom, $dateTo]);
            })
            ->whereHas('account', function($q) {
                $q->where('type', 'expense');
            })
            ->get();

        $totalExpense = (float) $expenseLines->sum('debit_base_amount') - (float) $expenseLines->sum('credit_base_amount');

        return [
            'gross_revenue' => $totalRevenue,
            'total_expense' => $totalExpense,
            'net_profit'    => $totalRevenue - $totalExpense,
        ];
    }

    /**
     * Depodaki Ürünlerin Stok Değerini Hesapla (Ortalama Maliyet veya Son Maliyet Üzerinden).
     */
    public function getWarehouseStockValue(int $userId, ?int $warehouseId = null): array
    {
        $balances = StockBalance::where('user_id', $userId)
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->get();

        $totalValue = 0.0;
        $totalItems = 0;

        foreach ($balances as $balance) {
            if ($balance->quantity <= 0) {
                continue;
            }

            // Son maliyet tespiti
            $lastMovement = StockMovement::where('user_id', $userId)
                ->where('stock_code', $balance->stock_code)
                ->where('direction', 'in')
                ->whereNotNull('unit_cost')
                ->orderByDesc('id')
                ->first();

            $cost = $lastMovement ? (float) $lastMovement->unit_cost : 10.00; // Default fallback cost

            $itemValue = $balance->quantity * $cost;
            $totalValue += $itemValue;
            $totalItems += $balance->quantity;
        }

        return [
            'total_value' => $totalValue,
            'total_items' => $totalItems,
        ];
    }

    /**
     * Mizan Raporu (Trial Balance).
     */
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

    /**
     * Bilanço Raporu (Balance Sheet).
     */
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
