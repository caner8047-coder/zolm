<?php

namespace App\Livewire\Accounting;

use Livewire\Component;
use App\Services\Accounting\ReportService;

class AccountingDashboard extends Component
{
    public function getKpisProperty()
    {
        $userId = auth()->id();
        $reportService = app(ReportService::class);

        try {
            $agedReceivables = $reportService->getAgedReceivables($userId);
            $openReceivables = $agedReceivables['total'] ?? 0.0;
        } catch (\Exception $e) {
            $openReceivables = 0.0;
        }

        try {
            $agedPayables = $reportService->getAgedPayables($userId);
            $openPayables = $agedPayables['total'] ?? 0.0;
        } catch (\Exception $e) {
            $openPayables = 0.0;
        }

        try {
            $cashFlow = $reportService->getCashFlowForecast($userId);
            $cashBank = $cashFlow['total_liquidity'] ?? 0.0;
        } catch (\Exception $e) {
            $cashBank = 0.0;
        }

        try {
            $stockVal = $reportService->getWarehouseStockValue($userId);
            $stockValue = $stockVal['total_value'] ?? 0.0;
        } catch (\Exception $e) {
            $stockValue = 0.0;
        }

        return [
            'open_receivables' => $openReceivables,
            'open_payables' => $openPayables,
            'cash_bank' => $cashBank,
            'stock_value' => $stockValue,
        ];
    }

    public function render()
    {
        return view('livewire.accounting.accounting-dashboard')
            ->layout('layouts.app');
    }
}
