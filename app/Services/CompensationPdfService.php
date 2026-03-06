<?php

namespace App\Services;

use App\Models\Compensation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class CompensationPdfService
{
    /**
     * Dilekçe PDF oluştur
     */
    public function generatePetition(Compensation $compensation)
    {
        $settings = new \App\Services\MpSettingsService();
        $pdf = Pdf::loadView('pdf.compensation.petition', compact('compensation', 'settings'));
        return $pdf->output();
    }

    /**
     * Form PDF oluştur
     */
    /**
     * Form PDF oluştur
     */
    public function generateForm(Compensation $compensation)
    {
        $data = $this->prepareFormData($compensation);
        $pdf = Pdf::loadView('pdf.compensation.form', compact('data', 'compensation'));
        return $pdf->output();
    }

    private function prepareFormData(Compensation $compensation): array
    {
        $settings = new \App\Services\MpSettingsService();
        $companyName = $settings->get('company.name', 'Firma Adı Girilmemiş');
        $address = $settings->get('company.address', 'Adres Girilmemiş');
        $phone = $settings->get('company.phone', 'Telefon Girilmemiş');
        $email = $settings->get('company.email', 'E-posta Girilmemiş');
        $manager = $settings->get('company.manager', 'Yetkili Girilmemiş');
        $taxNumber = $settings->get('company.tax_number', 'Vergi No Girilmemiş');
        $taxOffice = $settings->get('company.tax_office', '');
        $iban = $settings->get('company.iban', 'IBAN Girilmemiş');
        $bank = $settings->get('company.bank', 'Banka Girilmemiş');
        $branch = $settings->get('company.branch', 'Şube Girilmemiş');
        $mersis = $settings->get('company.mersis', 'MERSİS Girilmemiş');

        return [
            "firmaBilgileri" => [
                "unvan" => $companyName,
                "adres" => $address,
                "telefon" => $phone,
                "email" => $email
            ],
            "basvuruBilgileri" => [
                "adSoyad" => $manager,
                "unvan" => "Şirket Yetkilisi",
                "tcKimlikNo" => $taxNumber,
                "basvuruYapanTaraf" => "Gonderici",
                "telefonNo" => $phone,
                "emailAdresi" => $email,
                "ibanNo" => $iban,
                "hesapSahibi" => $companyName,
                "banka" => $bank,
                "sube" => $branch,
                "vergiDairesiNumara" => $taxOffice . " - " . $taxNumber,
                "adres" => $address
            ],
            "mersisNumarasi" => $mersis,
            "kargoBilgileri" => [
                "temaNo" => "",
                "gonderiKodu" => $compensation->takip_kodu,
                "gonderiTarihi" => $compensation->tarih ? $compensation->tarih->format('d.m.Y') : '',
                "gondericiAdUnvan" => $companyName,
                "aliciAdUnvan" => $compensation->musteri_adi,
                "sevkIrsaliyeTarihi" => "",
                "sevkIrsaliyeNo" => "",
                "gonderiCevii" => $compensation->urun_adi
            ],
            "tazminBilgileri" => [
                "tazminNedeni" => $compensation->sebep_info['label'] ?? '',
                "tazminEdilenTutar" => number_format($compensation->talep_tutari, 2, ',', '.') . ' TL',
                "tazminNedeniAciklama" => $compensation->aciklama ?? ''
            ],
            "ekler" => [
                "Dilekçe",
                "Sevk İrsaliyesi",
                "Fatura"
            ],
            "tarih" => now()->format('Y-m-d'),
            "kvkkOnay" => true
        ];
    }

    /**
     * Tüm belgeleri ZIP olarak paketle
     */
    public function createZipPackage(Compensation $compensation): string
    {
        $zipFileName = 'tazmin_dosyasi_' . $compensation->id . '_' . date('YmdHis') . '.zip';
        $zipPath = storage_path('app/public/temp/' . $zipFileName);
        
        // Temp klasörünü oluştur
        if (!file_exists(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            
            // 1. Dilekçe Ekle
            $petitionPdf = $this->generatePetition($compensation);
            $zip->addFromString('Dilekce.pdf', $petitionPdf);

            // 2. Form Ekle
            $formPdf = $this->generateForm($compensation);
            $zip->addFromString('Tazmin_Formu.pdf', $formPdf);

            // 3. Ek Görselleri Ekle
            if (!empty($compensation->attachments)) {
                foreach ($compensation->attachments as $index => $path) {
                    if (Storage::disk('public')->exists($path)) {
                        $extension = pathinfo($path, PATHINFO_EXTENSION);
                        $fileName = 'Kanit_' . ($index + 1) . '.' . $extension;
                        $content = Storage::disk('public')->get($path);
                        $zip->addFromString($fileName, $content);
                    }
                }
            }

            $zip->close();
        }

        return $zipPath;
    }
}
