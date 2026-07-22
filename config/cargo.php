<?php

use App\Services\Cargo\ArasCargoConnector;
use App\Services\Cargo\DhlExpressCargoConnector;
use App\Services\Cargo\HepsiJetCargoConnector;
use App\Services\Cargo\PttCargoConnector;
use App\Services\Cargo\SuratCargoConnector;
use App\Services\Cargo\YurticiCargoConnector;

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
            'aliases' => ['surat', 'sürat', 'surat kargo', 'sürat kargo'],
            'tracking_url' => 'https://suratkargo.com.tr/Default/_KargoTakip?kargotakipno={tracking}',
            'logo' => 'surat.png',
            'cikis_il' => 'Denizli',
            'integration_status' => 'active',
            'connector' => SuratCargoConnector::class,
            'capabilities' => ['create', 'cancel', 'track', 'invoice'],
            'setup_fields' => [
                ['key' => 'sender_username', 'label' => 'Gönderim kullanıcı adı', 'help' => 'Boş bırakılırsa müşteri kodu kullanılır.'],
                ['key' => 'sender_password', 'label' => 'Gönderim şifresi', 'type' => 'password', 'required' => true, 'secret' => true],
                ['key' => 'query_password', 'label' => 'Sorgulama / web servis şifresi', 'type' => 'password', 'secret' => true, 'help' => 'Boş bırakılırsa gönderim şifresi kullanılır.'],
                ['key' => 'cod_username', 'label' => 'Kapıda ödeme kullanıcı adı'],
                ['key' => 'cod_password', 'label' => 'Kapıda ödeme şifresi', 'type' => 'password', 'secret' => true],
                ['key' => 'branch_code', 'label' => 'Şube kodu'],
                ['key' => 'test_reference', 'label' => 'Test gönderi / kargo anahtarı', 'help' => 'Bağlantı testinde gerçek bir kayıtla doğrulama yapmak için isteğe bağlıdır.'],
                ['key' => 'api_base_url', 'label' => 'Gönderim API adresi', 'type' => 'url', 'required' => true],
                ['key' => 'query_base_url', 'label' => 'Sorgulama API adresi', 'type' => 'url', 'required' => true],
                ['key' => 'soap_wsdl_url', 'label' => 'SOAP WSDL adresi', 'type' => 'url'],
            ],
        ],
        'yurtici' => [
            'name' => 'Yurtiçi Kargo',
            'code' => 'YURTICI',
            'aliases' => ['yurtici', 'yurtiçi', 'yurtici kargo', 'yurtiçi kargo'],
            'tracking_url' => 'https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code={tracking}',
            'logo' => 'yurtici.png',
            'cikis_il' => 'Denizli',
            'integration_status' => 'available',
            'connector' => YurticiCargoConnector::class,
            'capabilities' => ['create', 'cancel', 'track'],
            'setup_fields' => [
                ['key' => 'username', 'label' => 'Web servis kullanıcı adı', 'required' => true],
                ['key' => 'password', 'label' => 'Web servis şifresi', 'type' => 'password', 'required' => true, 'secret' => true],
                ['key' => 'test_reference', 'label' => 'Test gönderi / kargo anahtarı', 'help' => 'İsteğe bağlı; bağlantı testinde gerçek bir kayıtla doğrulama sağlar.'],
                ['key' => 'wsdl_url', 'label' => 'Özel WSDL adresi', 'type' => 'url', 'help' => 'Kargo firması farklı bir servis adresi verdiyse girin.'],
            ],
        ],
        'aras' => [
            'name' => 'Aras Kargo',
            'code' => 'ARAS',
            'aliases' => ['aras', 'aras kargo'],
            'tracking_url' => 'https://kargotakip.araskargo.com.tr/mainpage.aspx?code={tracking}',
            'logo' => 'aras.png',
            'cikis_il' => 'Denizli',
            'integration_status' => 'available',
            'connector' => ArasCargoConnector::class,
            'capabilities' => ['create', 'cancel', 'track'],
            'setup_fields' => [
                ['key' => 'username', 'label' => 'Web servis kullanıcı adı', 'required' => true],
                ['key' => 'password', 'label' => 'Web servis şifresi', 'type' => 'password', 'required' => true, 'secret' => true],
                ['key' => 'test_reference', 'label' => 'Test entegrasyon kodu', 'help' => 'İsteğe bağlı; bağlantı testinde gerçek bir kayıtla doğrulama sağlar.'],
                ['key' => 'wsdl_url', 'label' => 'Özel WSDL adresi', 'type' => 'url', 'help' => 'Kargo firması farklı bir servis adresi verdiyse girin.'],
            ],
        ],
        'ptt' => [
            'name' => 'PTT Kargo',
            'code' => 'PTT',
            'aliases' => ['ptt', 'ptt kargo'],
            'tracking_url' => 'https://gonderitakip.ptt.gov.tr/?q={tracking}',
            'logo' => 'ptt.png',
            'cikis_il' => 'Denizli',
            'integration_status' => 'available',
            'connector' => PttCargoConnector::class,
            'capabilities' => ['create', 'cancel', 'track'],
            'setup_fields' => [
                ['key' => 'customer_id', 'label' => 'Kullanıcı adı / Müşteri No', 'required' => true, 'help' => 'PTT tarafından verilen 9 veya 10 haneli müşteri numarası.'],
                ['key' => 'password', 'label' => 'Şifre', 'type' => 'password', 'required' => true, 'secret' => true],
                ['key' => 'barcode_start', 'label' => 'İlk barkod aralığı', 'required' => true, 'help' => 'PTT tarafından verilen 12 haneli ilk değer. Kontrol hanesini ZOLM hesaplar.'],
                ['key' => 'barcode_end', 'label' => 'Son barkod aralığı', 'required' => true, 'help' => 'PTT tarafından verilen 12 haneli son değer.'],
                ['key' => 'postal_cheque_number', 'label' => 'Posta Çeki No', 'help' => 'Varsa 8 haneli gönderici posta çeki numarası.'],
                ['key' => 'send_receiver_sms', 'label' => 'Alıcıya SMS gönder', 'type' => 'checkbox', 'default' => false],
                ['key' => 'upload_wsdl_url', 'label' => 'Özel veri yükleme WSDL', 'type' => 'url', 'help' => 'PTT farklı bir hesap adresi verdiyse girin.'],
                ['key' => 'tracking_wsdl_url', 'label' => 'Özel takip WSDL', 'type' => 'url', 'help' => 'PTT farklı bir takip adresi verdiyse girin.'],
            ],
        ],
        'hepsijet' => [
            'name' => 'HepsiJet',
            'code' => 'HEPSIJET',
            'aliases' => ['hepsijet', 'hepsi jet'],
            'tracking_url' => 'https://www.hepsijet.com/gonderi-takibi/{tracking}',
            'logo' => 'hepsijet.png',
            'cikis_il' => 'Denizli',
            'integration_status' => 'available',
            'connector' => HepsiJetCargoConnector::class,
            'capabilities' => ['create', 'cancel', 'track'],
            'setup_fields' => [
                ['key' => 'username', 'label' => 'API kullanıcı adı', 'required' => true],
                ['key' => 'password', 'label' => 'API şifresi', 'type' => 'password', 'required' => true, 'secret' => true],
                ['key' => 'company_name', 'label' => 'HepsiJet firma adı', 'required' => true],
                ['key' => 'company_code', 'label' => 'Firma kısaltma kodu', 'required' => true],
                ['key' => 'sender_address_id', 'label' => 'Gönderici adres ID', 'required' => true],
                ['key' => 'crossdock_code', 'label' => 'Cross-dock kodu', 'required' => true],
                ['key' => 'product_code', 'label' => 'Ürün kodu', 'default' => 'HX_STD'],
                ['key' => 'base_url', 'label' => 'Özel servis adresi', 'type' => 'url', 'help' => 'HepsiJet hesabınıza özel bir adres verdiyse girin.'],
            ],
        ],
        'dhl_express' => [
            'name' => 'DHL Express',
            'code' => 'DHL_EXPRESS',
            'aliases' => ['dhl', 'dhl express', 'dhl kargo'],
            'tracking_url' => 'https://www.dhl.com/tr-tr/home/tracking.html?tracking-id={tracking}',
            'logo' => 'dhl-express.png',
            'cikis_il' => 'Denizli',
            'integration_status' => 'available',
            'connector' => DhlExpressCargoConnector::class,
            'capabilities' => ['create', 'track'],
            'setup_fields' => [
                ['key' => 'username', 'label' => 'MyDHL API kullanıcı adı', 'required' => true],
                ['key' => 'password', 'label' => 'MyDHL API şifresi', 'type' => 'password', 'required' => true, 'secret' => true],
                ['key' => 'account_number', 'label' => 'DHL müşteri numarası', 'required' => true],
                ['key' => 'shipper_country_code', 'label' => 'Gönderici ülke kodu', 'default' => 'TR', 'required' => true],
                ['key' => 'shipper_postal_code', 'label' => 'Gönderici posta kodu', 'required' => true],
                ['key' => 'shipper_city', 'label' => 'Gönderici şehir', 'default' => 'Denizli', 'required' => true],
                ['key' => 'product_code', 'label' => 'DHL ürün kodu', 'default' => 'N'],
                ['key' => 'content_description', 'label' => 'Varsayılan içerik açıklaması', 'default' => 'Documents'],
            ],
        ],
        'trendyol_express' => [
            'name' => 'Trendyol Express',
            'code' => 'TRENDYOL_EXPRESS',
            'aliases' => ['trendyol express', 'trendyolexpress', 'tex'],
            'tracking_url' => 'https://www.trendyol.com/siparislerim',
            'logo' => 'trendyol-express.png',
            'cikis_il' => 'Denizli',
            'integration_status' => 'marketplace_managed',
            'capabilities' => [],
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
    | API Entegrasyonları
    |--------------------------------------------------------------------------
    | Sürat Kargo resmi REST servis adresleri varsayılan olarak tanımlıdır.
    | Gerekirse .env veya hesap bazlı panel ayarlarıyla canlı/prova ortamı
    | değiştirilebilir. Şifreler kesinlikle burada tutulmaz.
    */
    'integrations' => [
        'surat' => [
            'base_url' => env('SURAT_CARGO_API_BASE_URL', 'https://api01.suratkargo.com.tr'),
            'query_base_url' => env('SURAT_CARGO_QUERY_BASE_URL', env('SURAT_CARGO_API_BASE_URL', 'https://api01.suratkargo.com.tr')),
            'soap_url' => env('SURAT_CARGO_SOAP_URL', 'https://webservices.suratkargo.com.tr/services.asmx'),
            'soap_wsdl_url' => env('SURAT_CARGO_SOAP_WSDL_URL', 'https://webservices.suratkargo.com.tr/services.asmx?WSDL'),
            'test_base_url' => env('SURAT_CARGO_TEST_API_BASE_URL', 'https://api02.suratkargo.com.tr'),
            'test_soap_url' => env('SURAT_CARGO_TEST_SOAP_URL', 'https://prova.suratkargo.com.tr/services.asmx'),
            'test_soap_wsdl_url' => env('SURAT_CARGO_TEST_SOAP_WSDL_URL', 'https://prova.suratkargo.com.tr/services.asmx?WSDL'),
            'timeout' => (int) env('SURAT_CARGO_TIMEOUT', 30),
            'vat_rate' => (float) env('SURAT_CARGO_VAT_RATE', 0.20),
            'endpoints' => [
                'test_connection' => env('SURAT_CARGO_TEST_ENDPOINT', '/api/KargoTakipHareketDetayi'),
                'create_shipment' => env('SURAT_CARGO_CREATE_ENDPOINT', '/api/GonderiyiKargoyaGonder'),
                'cancel_shipment' => env('SURAT_CARGO_CANCEL_ENDPOINT', '/api/GonderiSil'),
                'recall_shipment' => env('SURAT_CARGO_RECALL_ENDPOINT', '/api/GonderiGeriCek'),
                'track_shipment' => env('SURAT_CARGO_TRACK_ENDPOINT', '/api/KargoTakipHareketDetayi'),
                'sent_shipment_details' => env('SURAT_CARGO_SENT_DETAILS_ENDPOINT', '/api/BarkodDetay/GonderilenKargoDetayi'),
                'multi_tracking' => env('SURAT_CARGO_MULTI_TRACKING_ENDPOINT', '/api/KargoTakipHareketCoklu'),
                'invoice_lines' => env('SURAT_CARGO_INVOICE_ENDPOINT'),
            ],
            'methods' => [
                'test_connection' => env('SURAT_CARGO_TEST_METHOD', 'POST'),
                'create_shipment' => env('SURAT_CARGO_CREATE_METHOD', 'POST'),
                'cancel_shipment' => env('SURAT_CARGO_CANCEL_METHOD', 'POST'),
                'recall_shipment' => env('SURAT_CARGO_RECALL_METHOD', 'POST'),
                'track_shipment' => env('SURAT_CARGO_TRACK_METHOD', 'POST'),
                'sent_shipment_details' => env('SURAT_CARGO_SENT_DETAILS_METHOD', 'POST'),
                'multi_tracking' => env('SURAT_CARGO_MULTI_TRACKING_METHOD', 'POST'),
                'invoice_lines' => env('SURAT_CARGO_INVOICE_METHOD', 'POST'),
            ],
        ],
        'yurtici' => [
            'test_wsdl' => env('YURTICI_CARGO_TEST_WSDL', 'https://testwebservices.yurticikargo.com/KOPSWebServices/ShippingOrderDispatcherServices?wsdl'),
            'live_wsdl' => env('YURTICI_CARGO_LIVE_WSDL', 'https://ws.yurticikargo.com/KOPSWebServices/ShippingOrderDispatcherServices?wsdl'),
        ],
        'aras' => [
            'test_wsdl' => env('ARAS_CARGO_TEST_WSDL', 'https://customerservicestest.araskargo.com.tr/arascargoservice/arascargoservice.asmx?WSDL'),
            'live_wsdl' => env('ARAS_CARGO_LIVE_WSDL', 'https://customerws.araskargo.com.tr/arascargoservice.asmx?WSDL'),
        ],
        'hepsijet' => [
            'test_base_url' => env('HEPSIJET_TEST_BASE_URL', 'https://integration-apitest.hepsijet.com'),
            'live_base_url' => env('HEPSIJET_LIVE_BASE_URL', 'https://integration.hepsijet.com'),
        ],
        'dhl_express' => [
            'test_base_url' => env('DHL_EXPRESS_TEST_BASE_URL', 'https://express.api.dhl.com/mydhlapi/test'),
            'live_base_url' => env('DHL_EXPRESS_LIVE_BASE_URL', 'https://express.api.dhl.com/mydhlapi'),
        ],
        'ptt' => [
            'test_upload_wsdl' => env('PTT_CARGO_TEST_UPLOAD_WSDL', 'https://pttws.ptt.gov.tr/PttVeriYuklemeTest/services/Sorgu?wsdl'),
            'live_upload_wsdl' => env('PTT_CARGO_LIVE_UPLOAD_WSDL', 'https://pttws.ptt.gov.tr/PttVeriYukleme/services/Sorgu?wsdl'),
            'tracking_wsdl' => env('PTT_CARGO_TRACKING_WSDL', 'https://pttws.ptt.gov.tr/GonderiHareketV2/services/Sorgu?wsdl'),
        ],
    ],

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
        'web_siparis_kodu' => ['WebSiparisKodu', 'Web Sipariş Kodu', 'Sipariş No', 'Order No'],
        'takip_no' => ['TakipNo', 'Takip No', 'Takip Numarası', 'Kargo Takip No'],
        'alici' => ['Alici', 'AliciUnvan', 'Alıcı', 'Alıcı Unvan', 'Müşteri', 'Müşteri Adı'],
        'gonderen' => ['GonderenUnvan', 'Gönderen', 'Gönderen Adı', 'Gönderen Unvan', 'Sender'],
        'borclu_unvan' => ['BorcluUnvan', 'Borçlu Unvan', 'Borçlu'],
        'adet' => ['Adet', 'Parça', 'Koli', 'Parça Sayısı'],
        'desi' => ['ToplamDesi', 'Toplam Desi', 'Desi', 'Hacim'],
        'tutar' => ['Tutar', 'Toplam Tutar', 'Kargo Ücreti', 'Ücret'],
        'cikis_il' => ['CikisIl', 'Çıkış İl', 'Çıkış İli', 'Gönderen İl'],
        'teslim_tarihi' => ['TeslimTarihi', 'Teslim Tarihi', 'Teslimat Tarihi'],
        'fatura_tarihi' => ['FaturaTarihi', 'Fatura Tarihi'],
        'tesellum_fatura_no' => ['TesellumdenFaturaNo', 'Tesellümden Fatura No', 'Tesellum Fatura No'],
        'barkod' => ['Barkod', 'Kargo Barkodu'],
        'alici_il' => ['AliciIlAdi', 'Alıcı İl', 'Alıcı İli'],
        'alici_ilce' => ['AliciIlceAdi', 'Alıcı İlçe', 'Alıcı İlçesi'],
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
