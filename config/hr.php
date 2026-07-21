<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HR Module Configuration
    |--------------------------------------------------------------------------
    */

    'modules' => [
        'personel' => ['enabled' => true, 'label' => 'Personel ve Organizasyon'],
        'izin' => ['enabled' => true, 'label' => 'İzin Yönetimi'],
        'vardiya' => ['enabled' => true, 'label' => 'Vardiya'],
        'pdks' => ['enabled' => true, 'label' => 'PDKS'],
        'puantaj' => ['enabled' => true, 'label' => 'Puantaj'],
        'bordro' => ['enabled' => true, 'label' => 'Bordro'],
        'masraf' => ['enabled' => true, 'label' => 'Masraf Yönetimi'],
        'avans' => ['enabled' => true, 'label' => 'Avans Yönetimi'],
        'zimmet' => ['enabled' => true, 'label' => 'Zimmet Yönetimi'],
        'ucret' => ['enabled' => true, 'label' => 'Ücret ve Yan Haklar'],
        'performans' => ['enabled' => true, 'label' => 'Performans'],
        'aday_takip' => ['enabled' => true, 'label' => 'Aday Takip'],
        'egitim' => ['enabled' => true, 'label' => 'Eğitim'],
        'baglilik' => ['enabled' => true, 'label' => 'Çalışan Bağlılığı'],
        'analitik' => ['enabled' => true, 'label' => 'İK Analitiği'],
        'destek' => ['enabled' => true, 'label' => 'Çalışan Destek Merkezi'],
        'isg' => ['enabled' => true, 'label' => 'İSG ve Uyum'],
    ],

    'file' => [
        'disk' => 'private',
        'max_size_bytes' => 20 * 1024 * 1024, // 20MB
        'allowed_mimes' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
        ],
    ],

    'employee_number' => [
        'prefix' => 'EMP',
        'length' => 5,
    ],

    'encryption_key' => null, // null ise app.key kullanılır

    'malware_scanner' => [
        // fail_closed: gerçek tarayıcı yok; production'da unavailable/error => upload engellenir.
        // fake_clean: yalnızca testing/local'da clean döner (gerçek tarama yapmaz).
        // off: tarayıcı devre dışı; unavailable döner.
        'mode' => env('HR_MALWARE_SCANNER_MODE', 'fail_closed'),
        // null => otomatik: production ortamında true, diğerlerinde false.
        // Açık true: her ortamda fail-closed. Açık false: hiçbir ortamda engelleme (önerilmez).
        'fail_closed' => env('HR_MALWARE_SCANNER_FAIL_CLOSED', null),
    ],
];
