<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Meta Cloud API Ayarları
    |--------------------------------------------------------------------------
    |
    | Graph API versiyonu ve base URL'i. Hiçbir service'de hard-code edilmez.
    |
    */
    'meta' => [
        'graph_version' => env('WHATSAPP_META_GRAPH_VERSION', 'v25.0'),
        'graph_base_url' => env('WHATSAPP_META_GRAPH_BASE_URL', 'https://graph.facebook.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Ayarları
    |--------------------------------------------------------------------------
    |
    | Meta webhook HMAC doğrulaması için app_secret.
    | Booster webhook timestamp kontrolü için max_age_seconds.
    |
    */
    'webhook' => [
        'verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', ''),
        'app_secret' => env('WHATSAPP_META_APP_SECRET', ''),
        'booster_max_age_seconds' => 300,
        'meta_max_body_bytes' => 1048576, // 1MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Ayarları
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'outbox' => env('WHATSAPP_OUTBOX_QUEUE', 'default'),
        'webhook' => env('WHATSAPP_WEBHOOK_QUEUE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gönderim Kuralları
    |--------------------------------------------------------------------------
    |
    | Sessiz saat ve frekans limiti SADECE pazarlama amaçlı mesajlara uygulanır.
    | order_updates (kargo) ve stock_alert bu kurallardan muaftır.
    |
    */
    'sending' => [
        'max_per_second' => env('WHATSAPP_MAX_MESSAGES_PER_SECOND', 10),
        'quiet_hours_start' => env('WHATSAPP_QUIET_HOURS_START', '22:00'),
        'quiet_hours_end' => env('WHATSAPP_QUIET_HOURS_END', '08:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */
    'features' => [
        'whatsapp_enabled' => (bool) env('WHATSAPP_ENABLED', false),
        'test_mode' => (bool) env('WHATSAPP_TEST_MODE', true),
        'test_phone_numbers' => array_filter(explode(',', env('WHATSAPP_TEST_PHONE_NUMBERS', ''))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gelir Atıf Pencereleri
    |--------------------------------------------------------------------------
    */
    'attribution' => [
        'cart_recovery_days' => 7,
        'first_purchase_days' => 14,
        'campaign_days' => 7,
        'stock_notification_days' => 7,
        'birthday_days' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Kargo Bildirim Ayarları (wa_settings'den override edilir)
    |--------------------------------------------------------------------------
    */
    'shipping' => [
        'enabled' => true,
        'stages' => ['shipped', 'out_for_delivery', 'delivered'],
        'template_ids' => [
            'shipped' => null,
            'out_for_delivery' => null,
            'delivered' => null,
        ],
        'tracking_update_enabled' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sepet Kurtarma (wa_automation_configs'den override edilir)
    |--------------------------------------------------------------------------
    */
    'cart_recovery' => [
        'enabled' => false,
        'stages' => [
            ['delay_minutes' => 60, 'enabled' => true, 'template_id' => null, 'coupon_enabled' => false, 'coupon_type' => 'percent', 'coupon_value' => 0, 'minimum_spend' => 0, 'coupon_expiry_hours' => 48],
            ['delay_minutes' => 1440, 'enabled' => true, 'template_id' => null, 'coupon_enabled' => true, 'coupon_type' => 'percent', 'coupon_value' => 10, 'minimum_spend' => 100, 'coupon_expiry_hours' => 48],
            ['delay_minutes' => 4320, 'enabled' => true, 'template_id' => null, 'coupon_enabled' => false, 'coupon_type' => 'percent', 'coupon_value' => 0, 'minimum_spend' => 0, 'coupon_expiry_hours' => 48],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stok Hatırlatıcı (wa_automation_configs'den override edilir)
    |--------------------------------------------------------------------------
    */
    'stock_alert' => [
        'enabled' => false,
        'batch_size' => 10,
        'minimum_sellable_quantity' => 1,
        'template_id' => null,
        'quiet_hours_enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention Policy (wa_settings'den override edilir)
    |--------------------------------------------------------------------------
    */
    'retention' => [
        'webhook_events_days' => 90,
        'inbound_messages_days' => 180,
        'audit_logs_days' => 365,
        'outbox_completed_days' => 60,
        'delivery_logs_days' => 180,
        'anonymize_on_delete' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sipariş Onayı (wa_automation_configs'den override edilir)
    |--------------------------------------------------------------------------
    */
    'order_confirmation' => [
        'enabled' => false,
        'allowed_statuses' => ['processing', 'completed', 'on-hold'],
        'template_id' => null,
        'include_order_link' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | İade Bildirimleri (wa_automation_configs'den override edilir)
    |--------------------------------------------------------------------------
    */
    'returns' => [
        'enabled' => false,
        'stages' => [
            'return_requested' => ['enabled' => true, 'template_id' => null],
            'return_received' => ['enabled' => true, 'template_id' => null],
            'return_approved' => ['enabled' => true, 'template_id' => null],
            'return_rejected' => ['enabled' => false, 'template_id' => null],
            'return_needs_info' => ['enabled' => false, 'template_id' => null],
        ],
    ],
];
