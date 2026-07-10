<?php

namespace App\Livewire\Accounting;

use Livewire\Component;
use App\Services\Accounting\ReportService;

class AccountingDashboard extends Component
{
    public function getKpisProperty()
    {
        $userId        = auth()->id();
        $reportService = app(ReportService::class);

        try {
            $agedReceivables = $reportService->receivablesAging($userId);
            $openReceivables = $agedReceivables['total_open'] ?? 0.0;
        } catch (\Exception $e) {
            $openReceivables = 0.0;
        }

        try {
            $agedPayables = $reportService->payablesAging($userId);
            $openPayables = $agedPayables['total_open'] ?? 0.0;
        } catch (\Exception $e) {
            $openPayables = 0.0;
        }

        try {
            $cashFlow = $reportService->cashFlowForecast($userId, 30);
            $cashBank = $cashFlow['opening_cash_balance'] ?? 0.0;
        } catch (\Exception $e) {
            $cashBank = 0.0;
        }

        try {
            $stockVal   = $reportService->stockInventoryValue($userId);
            $stockValue = $stockVal['total_inventory_value'] ?? 0.0;
        } catch (\Exception $e) {
            $stockValue = 0.0;
        }

        return [
            'open_receivables' => $openReceivables,
            'open_payables'    => $openPayables,
            'cash_bank'        => $cashBank,
            'stock_value'      => $stockValue,
        ];
    }

    public function render()
    {
        return view('livewire.accounting.accounting-dashboard')
            ->layout('layouts.app');
    }
}
