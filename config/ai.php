<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider
    |--------------------------------------------------------------------------
    | Desteklenen: 'groq', 'openai', 'gemini'
    */
    'provider' => env('AI_PROVIDER', 'groq'),

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    | AI servisinin API anahtarı. Production'da mutlaka set edilmeli.
    | Groq: https://console.groq.com/keys
    | OpenAI: https://platform.openai.com/api-keys
    | Gemini: https://aistudio.google.com/apikey
    */
    'api_key' => env('AI_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Model
    |--------------------------------------------------------------------------
    | Kullanılacak AI modeli
    | Groq: llama-3.3-70b-versatile, llama-3.1-8b-instant, mixtral-8x7b-32768
    | OpenAI: gpt-4o, gpt-4o-mini, gpt-3.5-turbo
    | Gemini: gemini-2.0-flash, gemini-1.5-pro, gemini-1.5-flash
    */
    'model' => env('AI_MODEL', 'llama-3.3-70b-versatile'),

    /*
    |--------------------------------------------------------------------------
    | Max Tokens
    |--------------------------------------------------------------------------
    | AI yanıtındaki maksimum token sayısı
    */
    'max_tokens' => env('AI_MAX_TOKENS', 4000),

    /*
    |--------------------------------------------------------------------------
    | Temperature
    |--------------------------------------------------------------------------
    | AI yanıtlarının yaratıcılık seviyesi (0.0 - 2.0)
    | Düşük: Daha tutarlı, Yüksek: Daha yaratıcı
    */
    'temperature' => env('AI_TEMPERATURE', 0.7),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    | API isteği için maksimum bekleme süresi (saniye)
    */
    'timeout' => env('AI_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Demo Mode
    |--------------------------------------------------------------------------
    | API key yoksa demo mod aktif olur. Production'da false olmalı.
    | true: API key yoksa demo yanıt döner
    | false: API key yoksa hata fırlatır
    */
    'demo_mode' => env('AI_DEMO_MODE', false),
];
