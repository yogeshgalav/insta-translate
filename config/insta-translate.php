<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Model
    |--------------------------------------------------------------------------
    |
    | Supported models: "claude", "gemini"
    |
    */
    'default_model' => env('INSTA_TRANSLATE_MODEL', 'claude'),

    /*
    |--------------------------------------------------------------------------
    | Default Language
    |--------------------------------------------------------------------------
    |
    | The default language code to use as the base for translations.
    |
    */
    'default_language' => env('INSTA_TRANSLATE_DEFAULT_LANGUAGE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | API Keys
    |--------------------------------------------------------------------------
    */
    'claude_key' => env('INSTA_TRANSLATE_CLAUDE_KEY'),
    'gemini_key' => env('INSTA_TRANSLATE_GEMINI_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Language File Path
    |--------------------------------------------------------------------------
    |
    | The path where the JSON translation files are stored.
    |
    */
    'lang_path' => env('INSTA_TRANSLATE_LANG_PATH', base_path('lang')),

    /*
    |--------------------------------------------------------------------------
    | Retry Attempts
    |--------------------------------------------------------------------------
    |
    | The number of times to retry an API request if it fails.
    |
    */
    'retry_attempts' => env('INSTA_TRANSLATE_RETRY_ATTEMPTS', 3),

    /*
    |--------------------------------------------------------------------------
    | Retry Delay
    |--------------------------------------------------------------------------
    |
    | The number of seconds to wait before retrying a failed API request.
    |
    */
    'retry_delay_seconds' => (int) env('INSTA_TRANSLATE_RETRY_DELAY_SECONDS', 30),
];
