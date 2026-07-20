<?php

return [
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
