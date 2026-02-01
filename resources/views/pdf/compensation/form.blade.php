<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Tazmin Talep Formu</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
        .section-title { background-color: #eee; padding: 5px; font-weight: bold; border: 1px solid #000; margin-top: 15px; }
        .row { display: table; width: 100%; border-bottom: 1px solid #ccc; }
        .label { display: table-cell; width: 35%; padding: 4px; font-weight: bold; border-right: 1px solid #ccc; background-color: #f9f9f9; }
        .value { display: table-cell; width: 65%; padding: 4px; }
        .box { border: 1px solid #000; margin-bottom: 10px; }
        .footer { margin-top: 20px; font-size: 10px; }
        .signature-box { float: right; width: 200px; height: 80px; border: 1px solid #000; text-align: center; padding-top: 10px; margin-top: 10px; }
        .clearfix { clear: both; }
    </style>
</head>
<body>
    <div class="header">
        <h2>TAZMİN TALEP FORMU</h2>
    </div>

    <div class="box">
        <div class="section-title" style="margin-top: 0; border: none; border-bottom: 1px solid #000;">BAŞVURU SAHİBİ BİLGİLERİ</div>
        <div class="row">
            <div class="label">Firma Unvanı</div>
            <div class="value">{{ $data['firmaBilgileri']['unvan'] }}</div>
        </div>
        <div class="row">
            <div class="label">Yetkili Adı Soyadı / Unvan</div>
            <div class="value">{{ $data['basvuruBilgileri']['adSoyad'] }} / {{ $data['basvuruBilgileri']['unvan'] }}</div>
        </div>
        <div class="row">
            <div class="label">TC Kimlik No</div>
            <div class="value">{{ $data['basvuruBilgileri']['tcKimlikNo'] }}</div>
        </div>
        <div class="row">
            <div class="label">MERSİS No</div>
            <div class="value">{{ $data['mersisNumarasi'] }}</div>
        </div>
        <div class="row">
            <div class="label">Vergi Dairesi / No</div>
            <div class="value">{{ $data['basvuruBilgileri']['vergiDairesiNumara'] }}</div>
        </div>
        <div class="row">
            <div class="label">Adres</div>
            <div class="value">{{ $data['basvuruBilgileri']['adres'] }}</div>
        </div>
        <div class="row">
            <div class="label">Telefon / E-posta</div>
            <div class="value">{{ $data['basvuruBilgileri']['telefonNo'] }} / {{ $data['basvuruBilgileri']['emailAdresi'] }}</div>
        </div>
    </div>

    <div class="box">
        <div class="section-title" style="margin-top: 0; border: none; border-bottom: 1px solid #000;">BANKA HESAP BİLGİLERİ</div>
        <div class="row">
            <div class="label">Banka / Şube</div>
            <div class="value">{{ $data['basvuruBilgileri']['banka'] }} / {{ $data['basvuruBilgileri']['sube'] }}</div>
        </div>
        <div class="row">
            <div class="label">Hesap Sahibi</div>
            <div class="value">{{ $data['basvuruBilgileri']['hesapSahibi'] }}</div>
        </div>
        <div class="row" style="border-bottom: none;">
            <div class="label">IBAN</div>
            <div class="value">{{ $data['basvuruBilgileri']['ibanNo'] }}</div>
        </div>
    </div>

    <div class="box">
        <div class="section-title" style="margin-top: 0; border: none; border-bottom: 1px solid #000;">KARGO GÖNDERİ DETAYLARI</div>
        <div class="row">
            <div class="label">Taşıma Kodu (Takip No)</div>
            <div class="value">{{ $data['kargoBilgileri']['gonderiKodu'] }}</div>
        </div>
        <div class="row">
            <div class="label">Gönderi Tarihi</div>
            <div class="value">{{ $data['kargoBilgileri']['gonderiTarihi'] }}</div>
        </div>
        <div class="row">
            <div class="label">Alıcı Adı Soyadı</div>
            <div class="value">{{ $data['kargoBilgileri']['aliciAdUnvan'] }}</div>
        </div>
        <div class="row" style="border-bottom: none;">
            <div class="label">Ürün İçeriği</div>
            <div class="value">{{ $data['kargoBilgileri']['gonderiCevii'] }}</div>
        </div>
    </div>

    <div class="box">
        <div class="section-title" style="margin-top: 0; border: none; border-bottom: 1px solid #000;">TAZMİN TALEBİ</div>
        <div class="row">
            <div class="label">Tazmin Nedeni</div>
            <div class="value">{{ $data['tazminBilgileri']['tazminNedeni'] }}</div>
        </div>
        <div class="row">
            <div class="label">Talep Edilen Tutar</div>
            <div class="value">{{ $data['tazminBilgileri']['tazminEdilenTutar'] }}</div>
        </div>
        <div class="row" style="border-bottom: none;">
            <div class="label">Açıklama</div>
            <div class="value">{{ $data['tazminBilgileri']['tazminNedeniAciklama'] }}</div>
        </div>
    </div>

    <div class="footer">
        <p>Yukarıdaki bilgilerin doğruluğunu beyan eder, kargo gönderimindeki hasar/kayıp nedeniyle oluşan mağduriyetimizin giderilmesini talep ederiz.</p>
        <p><strong>KVKK Onayı:</strong> {{ $data['kvkkOnay'] ? 'Evet, kişisel verilerimin işlenmesini onaylıyorum.' : 'Hayır' }}</p>
        
        <div class="signature-box">
            <strong>Kaşe / İmza</strong><br>
            {{ $data['basvuruBilgileri']['unvan'] }}<br>
            {{ $data['basvuruBilgileri']['adSoyad'] }}<br>
            <br>
            {{ $data['tarih'] }}
        </div>
        <div class="clearfix"></div>
    </div>

    @if(!empty($data['ekler']))
    <div style="margin-top: 20px; font-size: 10px;">
        <strong>Ekler:</strong>
        <ul>
            @foreach($data['ekler'] as $ek)
                <li>{{ $ek }}</li>
            @endforeach
        </ul>
    </div>
    @endif
</body>
</html>
