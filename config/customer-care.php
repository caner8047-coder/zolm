<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ZOLM AI Müşteri İletişim Merkezi Özellik Bayrakları
    |--------------------------------------------------------------------------
    |
    | Bu dosya ZOLM AI Müşteri İletişim Merkezi modülünün ana ve alt özellik
    | bayraklarını yönetir. Güvenli varsayılan değerlerin tamamı kapalıdır.
    |
    */

    'enabled' => (bool) env('CUSTOMER_CARE_ENABLED', false),

    'force_https' => (bool) env('CUSTOMER_CARE_FORCE_HTTPS', env('APP_ENV', 'production') === 'production'),

    'inbox_enabled' => (bool) env('CUSTOMER_CARE_INBOX_ENABLED', false),

    'ai_copilot_enabled' => (bool) env('CUSTOMER_CARE_AI_COPILOT_ENABLED', false),

    'auto_reply_enabled' => (bool) env('CUSTOMER_CARE_AUTO_REPLY_ENABLED', false),

    'knowledge_enabled' => (bool) env('CUSTOMER_CARE_KNOWLEDGE_ENABLED', false),

    'analytics_enabled' => (bool) env('CUSTOMER_CARE_ANALYTICS_ENABLED', false),

    'demo_mode' => (bool) env('CUSTOMER_CARE_DEMO_MODE', false),

    'default_automation_mode' => env('CUSTOMER_CARE_DEFAULT_AUTOMATION_MODE', 'manual'),

    'queue' => env('CUSTOMER_CARE_QUEUE', 'default'),

    'system_actor_email' => env('CUSTOMER_CARE_SYSTEM_ACTOR_EMAIL', 'system@zolm.com'),

    'report_directory' => env('CUSTOMER_CARE_REPORT_DIRECTORY', base_path('docs/customer-care')),

    'pilot_dashboard_enabled' => (bool) env('CUSTOMER_CARE_PILOT_DASHBOARD_ENABLED', false),

    'settings_enabled' => (bool) env('CUSTOMER_CARE_SETTINGS_ENABLED', false),

    'pilot_store_allowlist' => array_filter(explode(',', env('CUSTOMER_CARE_PILOT_STORE_ALLOWLIST', ''))),

    'circuit_breaker_enabled' => (bool) env('CUSTOMER_CARE_CIRCUIT_BREAKER_ENABLED', false),

    'auto_reply_max_per_hour' => (int) env('CUSTOMER_CARE_AUTO_REPLY_MAX_PER_HOUR', 0),

    'max_dispatch_failures_15m' => (int) env('CUSTOMER_CARE_MAX_DISPATCH_FAILURES_15M', 3),

    'max_policy_blocks_15m' => (int) env('CUSTOMER_CARE_MAX_POLICY_BLOCKS_15M', 5),

    'golden_eval_max_age_days' => (int) env('CUSTOMER_CARE_GOLDEN_EVAL_MAX_AGE_DAYS', 7),

    'golden_eval_min_samples' => (int) env('CUSTOMER_CARE_GOLDEN_EVAL_MIN_SAMPLES', 20),

    'golden_eval_min_score' => (int) env('CUSTOMER_CARE_GOLDEN_EVAL_MIN_SCORE', 80),

    'golden_eval_min_source_accuracy' => (int) env('CUSTOMER_CARE_GOLDEN_EVAL_MIN_SOURCE_ACCURACY', 95),

    'product_question_backfill_days' => (int) env('CUSTOMER_CARE_PRODUCT_QUESTION_BACKFILL_DAYS', 365),

    'shadow_min_samples' => (int) env('CUSTOMER_CARE_SHADOW_MIN_SAMPLES', 20),

    'shadow_min_average' => (int) env('CUSTOMER_CARE_SHADOW_MIN_AVERAGE', 80),

    'pilot_max_backlog' => (int) env('CUSTOMER_CARE_PILOT_MAX_BACKLOG', 10),

    'onboarding_verification_max_age_days' => (int) env('CUSTOMER_CARE_ONBOARDING_VERIFICATION_MAX_AGE_DAYS', 30),

    'connector_certification_max_age_days' => (int) env('CUSTOMER_CARE_CONNECTOR_CERTIFICATION_MAX_AGE_DAYS', 7),

    'production_readiness_max_age_minutes' => (int) env('CUSTOMER_CARE_PRODUCTION_READINESS_MAX_AGE_MINUTES', 60),

    'experiment_evidence_max_age_days' => (int) env('CUSTOMER_CARE_EXPERIMENT_EVIDENCE_MAX_AGE_DAYS', 30),

    'approval_max_age_minutes' => (int) env('CUSTOMER_CARE_APPROVAL_MAX_AGE_MINUTES', 1440),

    'meta_social_enabled' => (bool) env('CUSTOMER_CARE_META_SOCIAL_ENABLED', false),

    'google_reviews_enabled' => (bool) env('CUSTOMER_CARE_GOOGLE_REVIEWS_ENABLED', false),

    'web_chat_enabled' => (bool) env('CUSTOMER_CARE_WEB_CHAT_ENABLED', false),

    'onboarding_enabled' => (bool) env('CUSTOMER_CARE_ONBOARDING_ENABLED', false),

    'admin_center_enabled' => (bool) env('CUSTOMER_CARE_ADMIN_CENTER_ENABLED', false),

    'sales_copilot_enabled' => (bool) env('CUSTOMER_CARE_SALES_COPILOT_ENABLED', false),

    'cart_recovery_enabled' => (bool) env('CUSTOMER_CARE_CART_RECOVERY_ENABLED', false),

    'integration_hub_enabled' => (bool) env('CUSTOMER_CARE_INTEGRATION_HUB_ENABLED', false),

    'quality_center_enabled' => (bool) env('CUSTOMER_CARE_QUALITY_CENTER_ENABLED', false),

    'ops_center_enabled' => (bool) env('CUSTOMER_CARE_OPS_CENTER_ENABLED', false),

    'budget_cap_daily' => (float) env('CUSTOMER_CARE_BUDGET_CAP_DAILY', 10.0),

    'budget_cap_monthly' => (float) env('CUSTOMER_CARE_BUDGET_CAP_MONTHLY', 200.0),

    // P1-4: Mesai dışı otomatik cevap allowlist — varsayılan KAPALI (fail-closed)
    // true yapılmadan mesai dışı otomatik yanıt gönderilmez.
    'business_hours_auto_reply_enabled' => (bool) env('CUSTOMER_CARE_BUSINESS_HOURS_AUTO_REPLY', false),

    // Google reviews auto reply — varsayılan KAPALI
    'google_reviews_auto_reply_enabled' => (bool) env('CUSTOMER_CARE_GOOGLE_REVIEWS_AUTO_REPLY', false),

    'governance_enabled' => (bool) env('CUSTOMER_CARE_GOVERNANCE_ENABLED', false),

    'compliance_enabled' => (bool) env('CUSTOMER_CARE_COMPLIANCE_ENABLED', false),

    'reliability_enabled' => (bool) env('CUSTOMER_CARE_RELIABILITY_ENABLED', false),

    'launch_center_enabled' => (bool) env('CUSTOMER_CARE_LAUNCH_CENTER_ENABLED', false),

    'reconciliation_enabled' => (bool) env('CUSTOMER_CARE_RECONCILIATION_CENTER_ENABLED', false),

    'release_center_enabled' => (bool) env('CUSTOMER_CARE_RELEASE_CENTER_ENABLED', false),

    // Waves AN / AO / AP
    'success_center_enabled' => (bool) env('CUSTOMER_CARE_SUCCESS_CENTER_ENABLED', false),

    'experiments_enabled' => (bool) env('CUSTOMER_CARE_EXPERIMENTS_ENABLED', false),

    'security_center_enabled' => (bool) env('CUSTOMER_CARE_SECURITY_CENTER_ENABLED', false),

    // Waves AQ / AR / AS
    'org_center_enabled' => (bool) env('CUSTOMER_CARE_ORG_CENTER_ENABLED', false),

    'enterprise_api_enabled' => (bool) env('CUSTOMER_CARE_ENTERPRISE_API_ENABLED', false),

    'commercial_center_enabled' => (bool) env('CUSTOMER_CARE_COMMERCIAL_CENTER_ENABLED', false),

    // Waves AT / AU / AV
    'agent_workspace_enabled' => (bool) env('CUSTOMER_CARE_AGENT_WORKSPACE_ENABLED', false),

    'connector_certification_enabled' => (bool) env('CUSTOMER_CARE_CONNECTOR_CERTIFICATION_ENABLED', false),

    'production_center_enabled' => (bool) env('CUSTOMER_CARE_PRODUCTION_CENTER_ENABLED', false),

    'rate_limits' => [
        'whatsapp' => [
            'max_attempts' => (int) env('CUSTOMER_CARE_LIMIT_WA_ATTEMPTS', 100),
            'decay_seconds' => (int) env('CUSTOMER_CARE_LIMIT_WA_DECAY', 3600),
        ],
        'trendyol' => [
            'max_attempts' => (int) env('CUSTOMER_CARE_LIMIT_TY_ATTEMPTS', 50),
            'decay_seconds' => (int) env('CUSTOMER_CARE_LIMIT_TY_DECAY', 3600),
        ],
        'hepsiburada' => [
            'max_attempts' => (int) env('CUSTOMER_CARE_LIMIT_HB_ATTEMPTS', 50),
            'decay_seconds' => (int) env('CUSTOMER_CARE_LIMIT_HB_DECAY', 3600),
        ],
        'n11' => [
            'max_attempts' => (int) env('CUSTOMER_CARE_LIMIT_N11_ATTEMPTS', 50),
            'decay_seconds' => (int) env('CUSTOMER_CARE_LIMIT_N11_DECAY', 3600),
        ],
        'meta' => [
            'max_attempts' => (int) env('CUSTOMER_CARE_LIMIT_META_ATTEMPTS', 100),
            'decay_seconds' => (int) env('CUSTOMER_CARE_LIMIT_META_DECAY', 3600),
        ],
        'google_reviews' => [
            'max_attempts' => (int) env('CUSTOMER_CARE_LIMIT_GOOGLE_ATTEMPTS', 30),
            'decay_seconds' => (int) env('CUSTOMER_CARE_LIMIT_GOOGLE_DECAY', 3600),
        ],
        'web_chat' => [
            'max_attempts' => (int) env('CUSTOMER_CARE_LIMIT_CHAT_ATTEMPTS', 200),
            'decay_seconds' => (int) env('CUSTOMER_CARE_LIMIT_CHAT_DECAY', 3600),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SaaS Plan ve Kota Limitleri
    |--------------------------------------------------------------------------
    */
    'plans' => [
        'monthly_ai_drafts' => (int) env('CUSTOMER_CARE_LIMIT_AI_DRAFTS', 500),
        'monthly_auto_replies' => (int) env('CUSTOMER_CARE_LIMIT_AUTO_REPLIES', 200),
        'connected_channels' => (int) env('CUSTOMER_CARE_LIMIT_CHANNELS', 5),
        'retained_days' => (int) env('CUSTOMER_CARE_LIMIT_RETAINED_DAYS', 365),
        'knowledge_suggestions_per_day' => (int) env('CUSTOMER_CARE_LIMIT_KNOWLEDGE_SUGGESTIONS', 20),
    ],
];
