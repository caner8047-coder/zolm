<?php

/**
 * Kargo Yapılandırması
 * 
 * Kargo firmaları, takip URL'leri ve karşılaştırma ayarları
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Desteklenen Kargo Firmaları
    |--------------------------------------------------------------------------
    */
    'companies' => [
        'surat' => [
            'name' => 'Sürat Kargo',
            'code' => 'SURAT',
            'tracking_url' => 'https://suratkargo.com.tr/Default/_KargoTakip?kargotakipno={tracking}',
            'logo' => 'surat.png',
            'cikis_il' => 'Denizli',
        ],
        'mng' => [
            'name' => 'MNG Kargo',
            'code' => 'MNG',
            'tracking_url' => 'https://www.mngkargo.com.tr/gonderi-takip?q={tracking}',
            'logo' => 'mng.png',
            'cikis_il' => 'Denizli',
        ],
        'yurtici' => [
            'name' => 'Yurtiçi Kargo',
            'code' => 'YURTICI',
            'tracking_url' => 'https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code={tracking}',
            'logo' => 'yurtici.png',
            'cikis_il' => 'Denizli',
        ],
        'aras' => [
            'name' => 'Aras Kargo',
            'code' => 'ARAS',
            'tracking_url' => 'https://kargotakip.araskargo.com.tr/mainpage.aspx?code={tracking}',
            'logo' => 'aras.png',
            'cikis_il' => 'Denizli',
        ],
        'ptt' => [
            'name' => 'PTT Kargo',
            'code' => 'PTT',
            'tracking_url' => 'https://gonderitakip.ptt.gov.tr/?q={tracking}',
            'logo' => 'ptt.png',
            'cikis_il' => 'Denizli',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Varsayılan Kargo Firması
    |--------------------------------------------------------------------------
    */
    'default_company' => 'surat',

    /*
    |--------------------------------------------------------------------------
    | Sipariş/İade Ayrımı için Çıkış İli
    |--------------------------------------------------------------------------
    | Bu ilden çıkan kargolar "sipariş", diğerleri "iade" olarak işaretlenir.
    */
    'origin_city' => env('CARGO_ORIGIN_CITY', 'Denizli'),

    /*
    |--------------------------------------------------------------------------
    | Karşılaştırma Toleransları
    |--------------------------------------------------------------------------
    | Desi ve tutar karşılaştırmalarında kabul edilebilir fark miktarları
    */
    'tolerances' => [
        'desi' => (float) env('CARGO_TOLERANCE_DESI', 2.0),      // ±2 desi
        'tutar' => (float) env('CARGO_TOLERANCE_TUTAR', 5.0),   // ±5 TL
        'parca' => (int) env('CARGO_TOLERANCE_PARCA', 0),       // Parça toleransı yok
    ],

    /*
    |--------------------------------------------------------------------------
    | 100 Desi Kuralı
    |--------------------------------------------------------------------------
    | Tek kolide maksimum taşınabilir desi
    */
    'max_desi_per_parcel' => (int) env('CARGO_MAX_DESI', 100),

    /*
    |--------------------------------------------------------------------------
    | Excel Kolon Eşleştirmeleri - Kargo Raporu
    |--------------------------------------------------------------------------
    | Kargo firması Excel'indeki kolon adları
    */
    'cargo_columns' => [
        'takip_no' => ['TakipNo', 'Takip No', 'Takip Numarası', 'Kargo Takip No'],
        'alici' => ['Alici', 'Alıcı', 'Müşteri', 'Müşteri Adı'],
        'gonderen' => ['GonderenUnvan', 'Gönderen', 'Gönderen Adı', 'Gönderen Unvan', 'Sender'],
        'adet' => ['Adet', 'Parça', 'Koli', 'Parça Sayısı'],
        'desi' => ['ToplamDesi', 'Toplam Desi', 'Desi', 'Hacim'],
        'tutar' => ['Tutar', 'Toplam Tutar', 'Kargo Ücreti', 'Ücret'],
        'cikis_il' => ['CikisIl', 'Çıkış İl', 'Çıkış İli', 'Gönderen İl'],
        'teslim_tarihi' => ['TeslimTarihi', 'Teslim Tarihi', 'Teslimat Tarihi'],
        'durum' => ['TeslimatDurum', 'Teslimat Durum', 'Durum', 'Kargo Durumu'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Excel Kolon Eşleştirmeleri - Sipariş Listesi
    |--------------------------------------------------------------------------
    | Entegratör sipariş Excel'indeki kolon adları
    */
    'order_columns' => [
        'musteri' => ['Sevk - Müşteri', 'Müşteri', 'Müşteri Adı', 'Alıcı', 'Müşteri Sevk - [Fatura]'],
        'stok_kodu' => ['Stok Kodu', 'StokKodu', 'SKU', 'Ürün Kodu'],
        'urun_adi' => ['Ürün', 'Ürün Adı', 'Ürün Açıklaması'],
        'adet' => ['Adet', 'Miktar', 'Sipariş Adedi'],
        'pazaryeri' => ['Pazaryeri', 'Platform', 'Kanal'],
        'magaza' => ['Mağaza', 'Satıcı', 'Store'],
        'siparis_no' => ['Sipariş No', 'SiparişNo', 'Order No', 'Sipariş Numarası', 'Muhasebe Sip. No'],
        'kargo_takip' => ['Kargo Takip No', 'Takip No', 'Tracking', 'Sip. No', 'Gönderi No', 'Kargo No', 'Sevkiyat No'],
        'satir_fiyat' => ['Satır Fiyat', 'SatırFiyat', 'Satir Fiyat', 'Fiyat', 'Tutar', 'Ürün Fiyatı', 'Toplam Fiyat'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Müşteri Adı Eşleştirme Ayarları
    |--------------------------------------------------------------------------
    */
    'matching' => [
        'fuzzy_threshold' => (int) env('CARGO_FUZZY_THRESHOLD', 85), // %85 benzerlik
        'normalize_chars' => true,       // Türkçe karakterleri normalize et
        'ignore_case' => true,           // Büyük/küçük harf duyarsız
        'trim_whitespace' => true,       // Boşlukları temizle
    ],

    /*
    |--------------------------------------------------------------------------
    | Rapor Ayarları
    |--------------------------------------------------------------------------
    */
    'reports' => [
        'auto_save' => true,                    // Otomatik kaydet
        'retention_days' => 365,                // 1 yıl sakla
        'export_format' => 'xlsx',              // Excel formatı
        'date_format' => 'd.m.Y',               // Tarih formatı
    ],
];
