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
    |
    | API keys are managed directly by Laravel AI using standard env variables:
    | ANTHROPIC_API_KEY, GEMINI_API_KEY, etc.
    |
    */

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
    | Glossary Path
    |--------------------------------------------------------------------------
    |
    | Path to a glossary.json file that defines brand terms to never translate
    | and locale-specific overrides for certain terms.
    |
    */
    'glossary_path' => env('INSTA_TRANSLATE_GLOSSARY_PATH', base_path('lang/glossary.json')),

];
