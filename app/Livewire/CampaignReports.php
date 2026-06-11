<?php

namespace App\Livewire;

use App\Models\OptimizationReport;
use App\Services\BadgePricingService;
use App\Services\BasketDiscountCampaignService;
use App\Services\ExcelService;
use App\Services\FlashProductsService;
use App\Services\PlusCommissionService;
use App\Services\TariffOptimizerService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Log;

class CampaignReports extends Component
{
    use WithFileUploads;

    public $activeFilter = 'all'; // all, tariff, plus, badge, flash, basket_discount

    // Akıllı Excel yükleme
    public $uploadFile;
    public string $uploadReportName = '';
    public string $uploadMessage = '';
    public string $uploadMessageType = 'info'; // info, success, error
    public bool $isUploading = false;

    /**
     * Her kampanya tipinin ayırt edici kolon parmak izleri.
     * Eşleşme skoru en yüksek olan kampanya tipi seçilir.
     */
    protected array $campaignFingerprints = [
        'tariff' => [
            'label'   => 'Ürün Komisyon Tarifeleri',
            'columns' => [
                '2.Fiyat Üst Limiti', '2. Fiyat Üst Limiti', '2.FIYAT ÜST LİMİTİ',
                '3.Fiyat Üst Limiti', '3. Fiyat Üst Limiti', '3.FIYAT ÜST LİMİTİ',
                '4.Fiyat Üst Limiti', '4. Fiyat Üst Limiti', '4.FIYAT ÜST LİMİTİ',
                '2.KOMİSYON', '2. KOMİSYON', '2.Komisyon',
                '3.KOMİSYON', '3. KOMİSYON', '3.Komisyon',
                '4.KOMİSYON', '4. KOMİSYON', '4.Komisyon',
                '1.Fiyat Alt Limit', '1. Fiyat Alt Limit',
            ],
        ],
        'plus' => [
            'label'   => 'Plus Komisyon Tarifeleri',
            'columns' => [
                'Plus Fiyat Üst Limiti', 'PLUS FİYAT ÜST LİMİTİ',
                'Plus Fiyat Limit', 'PLUS FİYAT LİMİT',
                'Plus Komisyon Teklifi', 'PLUS KOMİSYON TEKLİFİ',
                'Plus Komisyon Oranı', 'PLUS KOMİSYON ORANI',
                'Plus Komisyon', 'Plus Fiyat',
            ],
        ],
        'badge' => [
            'label'   => 'Avantajlı Ürün Etiketleri',
            'columns' => [
                '1 YILDIZ ÜST FİYAT', '1 Yıldız Üst Fiyat',
                '1 YILDIZ ALT FİYAT', '1 Yıldız Alt Fiyat',
                '2 YILDIZ ÜST FİYAT', '2 Yıldız Üst Fiyat',
                '2 YILDIZ ALT FİYAT', '2 Yıldız Alt Fiyat',
                '3 YILDIZ ÜST FİYAT', '3 Yıldız Üst Fiyat',
            ],
        ],
        'flash' => [
            'label'   => 'Flaş Ürünler',
            'columns' => [
                '24 Saat Fiyat', '24 SAAT FİYAT',
                '24 Saat Flaş Fiyat', '24H FLAŞ FİYAT',
                '3 Saat Fiyat', '3 SAAT FİYAT',
                '3 Saat Flaş Fiyat', '3H FLAŞ FİYAT',
                'Flaş Komisyon Oranı', 'FLAŞ KOMİSYON ORANI',
                '24 Saat Flaş Başlangıç Tarihi', '24 SAAT FLAŞ BAŞLANGIÇ TARİHİ',
            ],
        ],
        'basket_discount' => [
            'label'   => 'Sepet İndirimi Kampanyaları',
            'columns' => [
                'Maksimum Girebileceğin Fiyat', 'MAKSİMUM GİREBİLECEĞİN FİYAT',
                'Kampanyalı Satış Fiyatı', 'KAMPANYALI SATIŞ FİYATI',
                'Mevcut Satış Fiyatı', 'MEVCUT SATIŞ FİYATI',
                'ListingId', 'LISTINGID',
                'Ürün Komisyon Tarifesi', 'ÜRÜN KOMİSYON TARİFESİ',
            ],
        ],
    ];

    public function mount()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }
    }

    public function setFilter($filter)
    {
        $this->activeFilter = $filter;
    }

    /**
     * Akıllı Excel yükleme: Dosyayı oku, kampanya tipini algıla, ilgili servise yönlendir.
     */
    public function analyzeUpload()
    {
        $this->validate([
            'uploadFile' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $this->isUploading = true;
        $this->uploadMessage = '';

        try {
            // 1. Excel başlıklarını oku
            $excelService = app(ExcelService::class);
            $data = $excelService->importOrderXls($this->uploadFile);

            if ($data->isEmpty()) {
                $this->uploadMessage = 'Dosya boş veya okunamadı. Lütfen geçerli bir kampanya Excel dosyası yükleyin.';
                $this->uploadMessageType = 'error';
                $this->isUploading = false;
                return;
            }

            $headers = array_keys($data->first());

            // 2. Kampanya tipini algıla
            $detectedType = $this->detectCampaignType($headers);

            if (!$detectedType) {
                $this->uploadMessage = 'Kampanya türü tespit edilemedi. Bu dosyanın kolon başlıkları bilinen kampanya formatlarıyla eşleşmiyor. Lütfen dosyayı ilgili kampanya sayfasından yükleyin.';
                $this->uploadMessageType = 'error';
                $this->isUploading = false;
                return;
            }

            // 3. İlgili servise yönlendir
            $result = $this->routeToService($detectedType, $this->uploadFile, $this->uploadReportName);

            $label = $this->campaignFingerprints[$detectedType]['label'];

            if ($result['success']) {
                $this->uploadMessage = "✅ {$label} olarak tanındı. {$result['message']}";
                $this->uploadMessageType = 'success';
                // İlgili filtreye otomatik geç
                $this->activeFilter = $detectedType;
            } else {
                $this->uploadMessage = "⚠️ {$label} olarak tanındı ama analiz sırasında hata oluştu: {$result['message']}";
                $this->uploadMessageType = 'error';
            }
        } catch (\Exception $e) {
            Log::error('CampaignReports: Akıllı yükleme hatası', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->uploadMessage = 'Beklenmeyen bir hata oluştu: ' . $e->getMessage();
            $this->uploadMessageType = 'error';
        }

        $this->isUploading = false;
        $this->reset('uploadFile', 'uploadReportName');
    }

    /**
     * Excel başlıklarına bakarak kampanya tipini algıla.
     * Her tip için eşleşen kolon sayısını (skor) hesaplar, en yüksek skoru alan tipi döner.
     */
    protected function detectCampaignType(array $headers): ?string
    {
        $normalizedHeaders = array_map(fn($h) => mb_strtolower(trim($h)), $headers);

        $scores = [];

        foreach ($this->campaignFingerprints as $type => $fingerprint) {
            $score = 0;
            foreach ($fingerprint['columns'] as $column) {
                if (in_array(mb_strtolower(trim($column)), $normalizedHeaders, true)) {
                    $score++;
                }
            }
            $scores[$type] = $score;
        }

        // En yüksek skoru bul
        arsort($scores);
        $bestType = array_key_first($scores);
        $bestScore = $scores[$bestType];

        // En az 1 ayırt edici kolon eşleşmeli
        if ($bestScore < 1) {
            return null;
        }

        Log::info('CampaignReports: Kampanya tipi algılandı', [
            'detected' => $bestType,
            'scores'   => $scores,
            'headers'  => $headers,
        ]);

        return $bestType;
    }

    /**
     * Algılanan kampanya tipine göre doğru analiz servisini çağır.
     */
    protected function routeToService(string $campaignType, $file, ?string $reportName): array
    {
        return match ($campaignType) {
            'tariff' => app(TariffOptimizerService::class)->analyze($file, $reportName ?: null),
            'plus'   => app(PlusCommissionService::class)->analyze($file, $reportName ?: null),
            'badge'  => app(BadgePricingService::class)->analyze($file, $reportName ?: null),
            'flash'  => app(FlashProductsService::class)->analyze($file, $reportName ?: null),
            'basket_discount' => app(BasketDiscountCampaignService::class)->analyze($file, $reportName ?: null),
            default  => ['success' => false, 'message' => 'Bilinmeyen kampanya türü.'],
        };
    }

    /**
     * Yükleme mesajını temizle
     */
    public function clearUploadMessage()
    {
        $this->uploadMessage = '';
    }

    public function deleteReport($id)
    {
        $report = OptimizationReport::where('user_id', auth()->id())->find($id);

        if ($report) {
            // İlişkili item'ları da sil
            $report->items()->delete();
            $report->delete();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Rapor başarıyla silindi.'
            ]);
        }
    }

    public function render()
    {
        $query = OptimizationReport::where('user_id', auth()->id());

        if ($this->activeFilter !== 'all') {
            $query->where('campaign_type', $this->activeFilter);
        }

        $reports = $query->orderByDesc('created_at')->get();

        return view('livewire.campaign-reports', [
            'reports' => $reports
        ]);
    }
}
