<?php

namespace App\Services\Cargo;

use App\Models\CargoCarrierAccount;
use App\Models\CargoReportLine;
use App\Models\CargoReportRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SuratReportArchiveService
{
    public function __construct(protected SuratCargoConnector $connector)
    {
    }

    public function fetchAndArchive(CargoCarrierAccount $account, string $startDate, string $endDate): array
    {
        $result = $this->connector->sentShipmentReport($account, $startDate, $endDate);
        $run = $this->archive($account, $result, $startDate, $endDate);

        $result['archive_run_id'] = $run?->id;

        return $result;
    }

    public function archive(CargoCarrierAccount $account, array $result, string $startDate, string $endDate): ?CargoReportRun
    {
        if (!Schema::hasTable('cargo_report_runs') || !Schema::hasTable('cargo_report_lines')) {
            return null;
        }

        $totals = $result['totals'] ?? [];
        $run = CargoReportRun::query()->create([
            'user_id' => $account->user_id,
            'legal_entity_id' => $account->legal_entity_id,
            'cargo_carrier_account_id' => $account->id,
            'carrier_code' => 'surat',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'source_endpoint' => 'GonderilenKargoDetayi + KargoTakipHareketCoklu',
            'row_count' => (int) ($totals['row_count'] ?? 0),
            'total_pieces' => (int) ($totals['pieces'] ?? 0),
            'total_desi' => (float) ($totals['desi'] ?? 0),
            'total_amount' => (float) ($totals['amount'] ?? 0),
            'measurement_amount' => (float) ($totals['measurement_amount'] ?? 0),
            'grand_total_amount' => (float) ($totals['total_amount'] ?? 0),
            'currency' => 'TRY',
            'status' => 'completed',
            'raw_payload' => [
                'pulled_at' => now()->toDateTimeString(),
                'row_count' => (int) ($totals['row_count'] ?? 0),
                'warnings' => $result['warnings'] ?? [],
            ],
        ]);

        foreach (($result['rows'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $reportDate = $this->reportDateForRow($row, $startDate);
            $lineHash = $this->reportLineHash($reportDate, $row);

            CargoReportLine::query()->updateOrCreate([
                'cargo_carrier_account_id' => $account->id,
                'report_date' => $reportDate,
                'line_hash' => $lineHash,
            ], [
                'cargo_report_run_id' => $run->id,
                'user_id' => $account->user_id,
                'legal_entity_id' => $account->legal_entity_id,
                'carrier_code' => 'surat',
                'tracking_number' => $row['tracking_number'] ?? null,
                'web_order_code' => $row['web_order_code'] ?? null,
                'sales_code' => $row['sales_code'] ?? null,
                'customer_name' => $row['customer_name'] ?? null,
                'sender_name' => $row['sender_name'] ?? null,
                'destination_city' => $row['destination_city'] ?? null,
                'destination_district' => $row['destination_district'] ?? null,
                'status' => $row['status'] ?? null,
                'status_code' => filled($row['status_code'] ?? null) ? (int) $row['status_code'] : null,
                'pieces' => (int) ($row['pieces'] ?? 0),
                'desi' => (float) ($row['desi'] ?? 0),
                'measurement_desi' => (float) ($row['measurement_desi'] ?? 0),
                'measurement_kg' => (float) ($row['measurement_kg'] ?? 0),
                'amount' => (float) ($row['amount'] ?? 0),
                'amount_source' => $row['amount_source'] ?? 'empty',
                'measurement_amount' => (float) ($row['measurement_amount'] ?? 0),
                'total_amount' => (float) ($row['total_amount'] ?? 0),
                'vat_amount' => (float) ($row['vat_amount'] ?? 0),
                'amount_without_vat' => (float) ($row['amount_without_vat'] ?? 0),
                'currency' => 'TRY',
                'document_date' => $this->parseCarrierDate($row['document_date'] ?? null),
                'carrier_created_at' => $this->parseCarrierDate($row['created_at'] ?? null),
                'last_event_at' => $this->parseCarrierDate($row['last_event_at'] ?? null),
                'delivered_at' => $this->parseCarrierDate($row['delivered_at'] ?? null),
                'delivered_to' => $row['delivered_to'] ?? null,
                'raw_payload' => $row['raw_payload'] ?? $row,
            ]);
        }

        return $run;
    }

    public function dailySummaries(int $userId, int $limit = 30): Collection
    {
        if (!Schema::hasTable('cargo_report_lines')) {
            return collect();
        }

        return CargoReportLine::query()
            ->where('user_id', $userId)
            ->where('carrier_code', 'surat')
            ->selectRaw('report_date, COUNT(*) as row_count, SUM(pieces) as pieces, SUM(desi) as desi, SUM(amount) as amount, SUM(measurement_amount) as measurement_amount, SUM(total_amount) as total_amount')
            ->groupBy('report_date')
            ->orderByDesc('report_date')
            ->limit($limit)
            ->get();
    }

    public function linesForDateQuery(int $userId, string $date): Builder
    {
        return CargoReportLine::query()
            ->where('user_id', $userId)
            ->where('carrier_code', 'surat')
            ->whereDate('report_date', Carbon::parse($date)->toDateString());
    }

    public function reportDateForRow(array $row, string $fallbackDate): string
    {
        $date = $this->parseCarrierDate($row['document_date'] ?? null)
            ?: $this->parseCarrierDate($row['created_at'] ?? null)
            ?: Carbon::parse($fallbackDate);

        return $date->toDateString();
    }

    public function parseCarrierDate(mixed $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        $value = trim((string) $value);

        try {
            if (preg_match('/^\d{2}[\/.]\d{2}[\/.]\d{4}$/', $value)) {
                $format = str_contains($value, '/') ? 'd/m/Y' : 'd.m.Y';
                return Carbon::createFromFormat($format, $value)?->startOfDay();
            }

            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function reportLineHash(string $reportDate, array $row): string
    {
        $identity = $row['tracking_number']
            ?: $row['web_order_code']
            ?: $row['sales_code']
            ?: implode('|', [
                $row['customer_name'] ?? '',
                $row['pieces'] ?? '',
                $row['desi'] ?? '',
                $row['total_amount'] ?? '',
            ]);

        return hash('sha256', $reportDate . '|' . $identity);
    }
}
