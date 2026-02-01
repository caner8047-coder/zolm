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
        $pdf = Pdf::loadView('pdf.compensation.petition', compact('compensation'));
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
        return [
            "firmaBilgileri" => [
                "unvan" => "Zem Dayanıklı Tüketim Malları İthalat İhracat Sanayi ve Ticaret Limited Şirketi",
                "adres" => "Eskihisar Mah. 8018 Sk. No:5 İç Kapı No:1",
                "telefon" => "0 507 298 40 85",
                "email" => "zemhomedestek@gmail.com"
            ],
            "basvuruBilgileri" => [
                "adSoyad" => "Cuma Yıldırım",
                "unvan" => "Şirket Sahibi",
                "tcKimlikNo" => "48244509286",
                "basvuruYapanTaraf" => "Gonderici",
                "telefonNo" => "0 507 298 40 85",
                "emailAdresi" => "zemhomedestek@gmail.com",
                "ibanNo" => "TR880020500009775679300001",
                "hesapSahibi" => "ZEM DAYANIKLI TÜKETİM MALLARI İTHALAT İHRACAT SANAYİ VE TİCARET LİMİTED ŞİRKETİ",
                "banka" => "Kuveyt Türk Katılım Bankası",
                "sube" => "Nazilli Şubesi",
                "vergiDairesiNumara" => "Gökpınar Vergi Dairesi - 997 166 2607",
                "adres" => "Eskihisar Mah. 8018 Sk. No:5 İç Kapı No:1"
            ],
            "mersisNumarasi" => "0997 1662 6070 0001",
            "kargoBilgileri" => [
                "temaNo" => "",
                "gonderiKodu" => $compensation->takip_kodu,
                "gonderiTarihi" => $compensation->tarih ? $compensation->tarih->format('d.m.Y') : '',
                "gondericiAdUnvan" => "Zem Dayanıklı Tüketim Malları İthalat İhracat Sanayi ve Ticaret Limited Şirketi",
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
