<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Ücret Pusulası</title>
    <style>
        @page { margin: 28px; }
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 11px; }
        h1 { margin: 0; font-size: 20px; }
        .muted { color: #64748b; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 14px; margin-bottom: 16px; }
        .grid { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .grid td { width: 50%; border: 1px solid #cbd5e1; padding: 8px; vertical-align: top; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th, table.lines td { border: 1px solid #cbd5e1; padding: 7px; }
        table.lines th { background: #f1f5f9; text-align: left; }
        .right { text-align: right; }
        .total { font-weight: bold; background: #f8fafc; }
        .footer { margin-top: 18px; border-top: 1px solid #cbd5e1; padding-top: 10px; font-size: 9px; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Ücret Hesap Pusulası</h1>
        <div class="muted">{{ $entity->name }} · {{ $period->name }}</div>
    </div>
    <table class="grid">
        <tr><td><strong>Çalışan</strong><br>{{ $record->employee?->full_name }}</td><td><strong>Sicil No</strong><br>{{ $record->employee?->employee_number }}</td></tr>
        <tr><td><strong>Dönem</strong><br>{{ $period->timesheetPeriod->starts_on->format('d.m.Y') }} – {{ $period->timesheetPeriod->ends_on->format('d.m.Y') }}</td><td><strong>Ödeme</strong><br>{{ $record->payrollProfile?->payment_method === 'bank' ? ($record->payrollProfile?->maskedIban() ?: 'Banka') : 'Nakit' }}</td></tr>
    </table>
    <table class="lines">
        <thead><tr><th>Kazançlar</th><th class="right">Tutar ({{ $trace['currency'] }})</th><th>Kesintiler</th><th class="right">Tutar ({{ $trace['currency'] }})</th></tr></thead>
        <tbody>
            <tr><td>Temel brüt ücret</td><td class="right">{{ number_format($trace['base_gross_cents']/100, 2, ',', '.') }}</td><td>SGK işçi payı</td><td class="right">{{ number_format($trace['employee_social_security_cents']/100, 2, ',', '.') }}</td></tr>
            <tr><td>Normal fazla çalışma</td><td class="right">{{ number_format(($trace['regular_overtime_gross_cents'] ?? 0)/100, 2, ',', '.') }}</td><td>İşsizlik işçi payı</td><td class="right">{{ number_format($trace['employee_unemployment_cents']/100, 2, ',', '.') }}</td></tr>
            <tr><td>Resmî tatil çalışması</td><td class="right">{{ number_format(($trace['holiday_work_gross_cents'] ?? 0)/100, 2, ',', '.') }}</td><td>Gelir vergisi</td><td class="right">{{ number_format($trace['income_tax_cents']/100, 2, ',', '.') }}</td></tr>
            <tr><td>Hafta tatili çalışması</td><td class="right">{{ number_format(($trace['weekly_rest_work_gross_cents'] ?? 0)/100, 2, ',', '.') }}</td><td>Damga vergisi</td><td class="right">{{ number_format($trace['stamp_tax_cents']/100, 2, ',', '.') }}</td></tr>
            <tr><td>Diğer kazançlar</td><td class="right">{{ number_format(($trace['additional_earning_cents'] ?? 0)/100, 2, ',', '.') }}</td><td>Diğer kesintiler</td><td class="right">{{ number_format((($trace['pre_tax_deduction_cents'] ?? 0)+($trace['post_tax_deduction_cents'] ?? 0))/100, 2, ',', '.') }}</td></tr>
            <tr class="total"><td>BRÜT TOPLAM</td><td class="right">{{ number_format($trace['gross_pay_cents']/100, 2, ',', '.') }}</td><td>TOPLAM KESİNTİ</td><td class="right">{{ number_format($trace['employee_deductions_cents']/100, 2, ',', '.') }}</td></tr>
            <tr class="total"><td colspan="3">NET ÖDENECEK</td><td class="right">{{ number_format($trace['net_pay_cents']/100, 2, ',', '.') }} {{ $trace['currency'] }}</td></tr>
        </tbody>
    </table>
    <table class="grid" style="margin-top:16px">
        <tr><td><strong>SGK matrahı</strong><br>{{ number_format($trace['social_security_base_cents']/100, 2, ',', '.') }}</td><td><strong>Gelir vergisi matrahı</strong><br>{{ number_format($trace['period_tax_base_cents']/100, 2, ',', '.') }}</td></tr>
        <tr><td><strong>Kümülatif matrah</strong><br>{{ number_format($trace['closing_tax_base_cents']/100, 2, ',', '.') }}</td><td><strong>İşveren toplam maliyeti</strong><br>{{ number_format($trace['employer_total_cost_cents']/100, 2, ',', '.') }}</td></tr>
    </table>
    <div class="footer">Hesap izi: {{ $record->calculation_hash }}<br>Bu belge onaylı ZOLM bordro hesap paketinden elektronik olarak üretilmiştir. Resmî beyan dosyası değildir.</div>
</body>
</html>
