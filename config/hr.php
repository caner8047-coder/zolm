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
        'ucret' => ['enabled' => true, 'label' => 'Ücret ve Yan Haklar'],
        'performans' => ['enabled' => true, 'label' => 'Performans'],
        'aday_takip' => ['enabled' => true, 'label' => 'Aday Takip'],
        'egitim' => ['enabled' => true, 'label' => 'Eğitim'],
        'baglilik' => ['enabled' => true, 'label' => 'Çalışan Bağlılığı'],
        'analitik' => ['enabled' => true, 'label' => 'İK Analitiği'],
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
];
