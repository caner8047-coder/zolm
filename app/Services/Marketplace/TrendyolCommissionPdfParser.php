<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Log;

/**
 * Trendyol resmi komisyon tablosu PDF'ini pdftotext + Python ile parse eder.
 *
 * Çalışma:
 *   1. scripts/parse_commission_pdf.py çalıştırılır (pdftotext -layout kullanır).
 *   2. Python çıktısı JSON formatında döner.
 *   3. PHP JSON'u decode edip satır dizisini döner.
 *
 * Gereksinimler:
 *   - Container'da poppler-utils (pdftotext) kurulu olmalı.
 *   - Container'da python3 kurulu olmalı.
 */
class TrendyolCommissionPdfParser
{
    /** @var string Python script yolu (container içi) */
    private string $scriptPath;

    public function __construct()
    {
        $this->scriptPath = base_path('scripts/parse_commission_pdf.py');
    }

    /**
     * @param  string $pdfPath Sunucudaki geçici dosya yolu
     * @return array{ok:bool, rows:array<int,array<string,mixed>>, total:int, error:?string}
     */
    public function parse(string $pdfPath): array
    {
        // Python binary
        $python = $this->findPython();
        if ($python === null) {
            return $this->error('python3 binary bulunamadı.');
        }

        if (! file_exists($this->scriptPath)) {
            return $this->error('Parse scripti bulunamadı: ' . $this->scriptPath);
        }

        if (! file_exists($pdfPath)) {
            return $this->error('PDF dosyası bulunamadı: ' . $pdfPath);
        }

        $cmd    = $python . ' ' . escapeshellarg($this->scriptPath) . ' ' . escapeshellarg($pdfPath) . ' 2>&1';
        $output = shell_exec($cmd);

        if ($output === null || $output === '') {
            return $this->error('Parser çıktısı boş döndü.');
        }

        $decoded = json_decode($output, true);

        if (! is_array($decoded)) {
            Log::warning('TrendyolCommissionPdfParser: JSON decode hatası', [
                'raw' => substr($output, 0, 500),
            ]);
            return $this->error('Parser çıktısı JSON değil: ' . substr($output, 0, 200));
        }

        if (! ($decoded['ok'] ?? false)) {
            return $this->error($decoded['error'] ?? 'Bilinmeyen hata');
        }

        return [
            'ok'    => true,
            'rows'  => $decoded['rows'] ?? [],
            'total' => (int) ($decoded['total'] ?? 0),
            'error' => null,
        ];
    }

    protected function findPython(): ?string
    {
        foreach (['/usr/bin/python3', '/usr/local/bin/python3'] as $p) {
            if (file_exists($p) && is_executable($p)) {
                return $p;
            }
        }
        $which = trim((string) shell_exec('which python3 2>/dev/null'));
        return $which !== '' ? $which : null;
    }

    /** @return array{ok:false, rows:array<int,never>, total:0, error:string} */
    private function error(string $msg): array
    {
        Log::warning('TrendyolCommissionPdfParser: ' . $msg);
        return ['ok' => false, 'rows' => [], 'total' => 0, 'error' => $msg];
    }
}
