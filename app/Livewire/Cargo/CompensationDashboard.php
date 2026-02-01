<?php

namespace App\Livewire\Cargo;

use App\Models\CargoReportItem;
use App\Models\Compensation;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Tazmin Dashboard Bileşeni
 * 
 * Tab 4: Tazmin
 * - Kargo harcamaları grafik paneli
 * - Son tespit edilen desi hataları
 * - Son oluşturulan tazminler
 * - Tazmin CRUD
 */
class CompensationDashboard extends Component
{
    use WithPagination;
    use \Livewire\WithFileUploads;

    // Görünüm modu: dashboard, all_errors, all_compensations
    public string $viewMode = 'dashboard';

    // Arama
    public string $search = '';



    // Yeni tazmin modalı
    public bool $showCreateModal = false;
    public array $newCompensation = [
        'tarih' => '',
        'musteri_adi' => '',
        'takip_kodu' => '',
        'urun_adi' => '',
        'cargo_company' => 'Sürat Kargo',
        'sebep' => 'desi_fazla',
        'aciklama' => '',
        'talep_tutari' => 0,
    ];
    
    // Görsel yükleme
    public $attachments = [];

    // Detay modalı
    public bool $showDetailModal = false;
    public ?int $viewingCompensationId = null;

    // Durum güncelleme
    public bool $showStatusModal = false;
    public ?int $updatingCompensationId = null;
    public string $newStatus = '';
    public float $onaylananTutar = 0;
    
    // Dilekçe Düzenleme
    public bool $showPetitionModal = false;
    public string $editingPetitionText = '';

    // Mesaj
    public string $message = '';
    public string $messageType = 'info';

    public function mount()
    {
        $this->newCompensation['tarih'] = now()->format('Y-m-d');
    }

    /**
     * Arama değiştiğinde
     */
    public function updatedSearch()
    {
        $this->resetPage();
    }

    /**
     * Tüm hataları göster
     */
    public function showAllErrors()
    {
        $this->viewMode = 'all_errors';
    }

    /**
     * Tüm tazminleri göster
     */
    public function showAllCompensations()
    {
        $this->viewMode = 'all_compensations';
    }

    /**
     * Dashboard'a dön
     */
    public function backToDashboard()
    {
        $this->viewMode = 'dashboard';
        $this->search = '';
    }

    /**
     * Son 10 desi hatası
     */
    #[Computed]
    public function recentErrors()
    {
        return CargoReportItem::with('cargoReport')
            ->where('has_error', true)
            ->whereIn('error_type', ['desi_fazla', 'tutar_fazla'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }

    /**
     * Son 10 tazmin
     */
    #[Computed]
    public function recentCompensations()
    {
        return Compensation::orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }

    /**
     * Tüm hatalar (paginated & search)
     */
    #[Computed]
    public function allErrors()
    {
        return CargoReportItem::with('cargoReport')
            ->where('has_error', true)
            ->whereIn('error_type', ['desi_fazla', 'tutar_fazla', 'desi_eksik', 'tutar_eksik', 'eslesmedi'])
            ->when($this->search, function($q) {
                $q->where(function($sub) {
                    $sub->where('musteri_adi', 'like', '%' . $this->search . '%')
                        ->orWhere('takip_kodu', 'like', '%' . $this->search . '%')
                        ->orWhere('urun_adi', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    }

    /**
     * Tüm tazminler (paginated & search)
     */
    #[Computed]
    public function allCompensations()
    {
        return Compensation::orderBy('created_at', 'desc')
            ->when($this->search, function($q) {
                $q->where(function($sub) {
                    $sub->where('musteri_adi', 'like', '%' . $this->search . '%')
                        ->orWhere('takip_kodu', 'like', '%' . $this->search . '%')
                        ->orWhere('kargo_referans_no', 'like', '%' . $this->search . '%');
                });
            })
            ->paginate(20);
    }
    
    // ... [grafik methodları aynı kalacak] ...

    /**
     * Tazmin oluştur
     */
    public function createCompensation()
    {
        $this->validate([
            'newCompensation.tarih' => 'required|date',
            'newCompensation.musteri_adi' => 'required|string|max:255',
            'newCompensation.sebep' => 'required',
            'newCompensation.talep_tutari' => 'required|numeric|min:0',
            'attachments.*' => 'nullable|image|max:5120', // 5MB max
        ]);

        try {
            // Dosyaları yükle
            $attachmentPaths = [];
            if (!empty($this->attachments)) {
                foreach ($this->attachments as $file) {
                    $path = $file->store('compensations', 'public');
                    $attachmentPaths[] = $path;
                }
            }

            Compensation::create([
                'user_id' => auth()->id(),
                'cargo_report_item_id' => $this->newCompensation['cargo_report_item_id'] ?? null,
                'tarih' => $this->newCompensation['tarih'],
                'musteri_adi' => $this->newCompensation['musteri_adi'],
                'takip_kodu' => $this->newCompensation['takip_kodu'],
                'urun_adi' => $this->newCompensation['urun_adi'],
                'stok_kodu' => $this->newCompensation['stok_kodu'] ?? null,
                'cargo_company' => $this->newCompensation['cargo_company'],
                'sebep' => $this->newCompensation['sebep'],
                'aciklama' => $this->newCompensation['aciklama'],
                'talep_tutari' => $this->newCompensation['talep_tutari'],
                'durum' => 'beklemede',
                'attachments' => !empty($attachmentPaths) ? $attachmentPaths : null,
            ]);

            $this->showCreateModal = false;
            $this->resetNewCompensation();
            $this->showMessage('Tazmin talebi ve görseller kaydedildi.', 'success');

        } catch (\Exception $e) {
            $this->showMessage('Hata: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Yeni tazmin formunu sıfırla
     */
    protected function resetNewCompensation()
    {
        $this->newCompensation = [
            'tarih' => now()->format('Y-m-d'),
            'musteri_adi' => '',
            'takip_kodu' => '',
            'urun_adi' => '',
            'stok_kodu' => '',
            'cargo_company' => 'Sürat Kargo',
            'sebep' => 'desi_fazla',
            'aciklama' => '',
            'talep_tutari' => 0,
            'cargo_report_item_id' => null,
        ];
        $this->attachments = [];
    }

    /**
     * Tazmin istatistikleri
     */
    #[Computed]
    public function stats()
    {
        return [
            'total' => Compensation::count(),
            'pending' => Compensation::pending()->count(),
            'completed' => Compensation::completed()->count(),
            'total_claimed' => Compensation::sum('talep_tutari'),
            'total_approved' => Compensation::sum('onaylanan_tutar'),
            'success_rate' => $this->calculateSuccessRate(),
        ];
    }

    /**
     * Başarı oranı hesapla
     */
    protected function calculateSuccessRate(): float
    {
        $completed = Compensation::completed()->count();
        if ($completed === 0) return 0;

        $successful = Compensation::whereIn('durum', ['onaylandi', 'kismi_onay', 'odendi'])->count();
        return round(($successful / $completed) * 100, 1);
    }



    /**
     * Tazmin oluştur modalını aç
     */
    public function openCreateModal(?int $errorItemId = null)
    {
        $this->resetNewCompensation();

        // Hata itemından oluşturuluyorsa bilgileri doldur
        if ($errorItemId) {
            $item = CargoReportItem::with('cargoReport')->find($errorItemId);
            if ($item) {
                $this->newCompensation = [
                    'tarih' => $item->tarih?->format('Y-m-d') ?? now()->format('Y-m-d'),
                    'musteri_adi' => $item->musteri_adi,
                    'takip_kodu' => $item->takip_kodu,
                    'urun_adi' => $item->urun_adi,
                    'stok_kodu' => $item->stok_kodu,
                    'cargo_company' => $item->cargoReport?->cargo_company ?? 'Sürat Kargo',
                    'sebep' => $this->mapErrorTypeToSebep($item->error_type),
                    'aciklama' => '',
                    'talep_tutari' => abs($item->tutar_fark),
                    'cargo_report_item_id' => $item->id,
                ];
            }
        }

        $this->showCreateModal = true;
    }

    /**
     * Hata tipini tazmin sebebine çevir
     */
    protected function mapErrorTypeToSebep(string $errorType): string
    {
        return match($errorType) {
            'desi_fazla' => 'desi_fazla',
            'tutar_fazla' => 'tutar_fazla',
            'parca_eksik' => 'kayip_urun',
            default => 'diger',
        };
    }

    /**
     * Tazmin oluştur
     */


    /**
     * Durum güncelleme modalını aç
     */
    public function openStatusModal(int $id)
    {
        $compensation = Compensation::find($id);
        if (!$compensation) return;

        $this->updatingCompensationId = $id;
        $this->newStatus = $compensation->durum;
        $this->onaylananTutar = $compensation->onaylanan_tutar;
        $this->showStatusModal = true;
    }

    /**
     * Durumu güncelle
     */
    public function updateStatus()
    {
        if (!$this->updatingCompensationId) return;

        try {
            $compensation = Compensation::find($this->updatingCompensationId);
            if (!$compensation) return;

            $compensation->update([
                'durum' => $this->newStatus,
                'onaylanan_tutar' => $this->onaylananTutar,
                'sonuc_tarihi' => in_array($this->newStatus, ['onaylandi', 'reddedildi', 'odendi', 'kapandi'])
                    ? now()->toDateString()
                    : null,
            ]);

            $this->showStatusModal = false;
            $this->updatingCompensationId = null;
            $this->showMessage('Durum güncellendi.', 'success');

        } catch (\Exception $e) {
            $this->showMessage('Hata: ' . $e->getMessage(), 'error');
        }
    }
    /**
     * AI ile dilekçe oluştur
     */
    public function generateAiPetition(int $id)
    {
        try {
            $compensation = Compensation::find($id);
            if (!$compensation) return;

            $aiService = new \App\Services\AIService();
            $dilekce = $aiService->generatePetitionText($compensation);

            // Veritabanına kaydet
            $compensation->update(['dilekce_icerigi' => $dilekce]);

            // Modalı güncelle
            $this->editingPetitionText = $dilekce;
            $this->showMessage('AI tarafından dilekçe taslağı oluşturuldu.', 'success');

        } catch (\Exception $e) {
            $this->showMessage('AI Hatası: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Dilekçe düzenleme modalını aç
     */
    public function openPetitionModal(int $id)
    {
        $compensation = Compensation::find($id);
        if (!$compensation) return;

        $this->updatingCompensationId = $id;
        $this->editingPetitionText = $compensation->dilekce_icerigi ?? "Dilekçe içeriği henüz oluşturulmadı. 'AI ile Oluştur' butonuna basarak taslak oluşturabilirsiniz.";
        $this->showPetitionModal = true;
    }

    /**
     * Dilekçe içeriğini kaydet
     */
    public function savePetitionText()
    {
        if (!$this->updatingCompensationId) return;

        $compensation = Compensation::find($this->updatingCompensationId);
        if ($compensation) {
            $compensation->update(['dilekce_icerigi' => $this->editingPetitionText]);
            $this->showMessage('Dilekçe içeriği güncellendi.', 'success');
            $this->showPetitionModal = false;
        }
    }

    /**
     * Tazmin detayını görüntüle
     */
    public function viewCompensation(int $id)
    {
        $this->viewingCompensationId = $id;
        $this->showDetailModal = true;
    }

    /**
     * Görüntülenen tazmin
     */
    #[Computed]
    public function viewingCompensation()
    {
        if (!$this->viewingCompensationId) return null;
        return Compensation::find($this->viewingCompensationId);
    }



    /**
     * Mesaj göster
     */
    protected function showMessage(string $message, string $type = 'info')
    {
        $this->message = $message;
        $this->messageType = $type;
    }

    public function render()
    {
        return view('livewire.cargo.compensation-dashboard');
    }
}
