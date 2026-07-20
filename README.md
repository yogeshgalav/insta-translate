# InstaTranslate for Laravel

A lightweight Laravel package that leverages AI (via `laravel/ai`) to automatically translate language strings for your application. It replaces traditional translation management platforms by using AI to directly sync and translate missing keys in your `lang/` files.

## Features

- **Automated Translation**: Diffs your base locale (`en.json`) against target locales and translates only the missing keys.
- **Batched Requests**: Chunks translations to prevent hitting context window limits of AI models.
- **Multiple AI Providers**: Use any provider supported by `laravel/ai` — Anthropic Claude, Google Gemini, OpenAI, and more.
- **Glossary Protection**: Define brand names, technical terms, and locale-specific overrides that the AI must respect.
- **Context-Aware**: Pass domain context (e.g., "SaaS billing dashboard") so the AI produces more accurate translations.
- **PHP Array Files**: Translate both JSON (`lang/en.json`) and PHP array (`lang/en/auth.php`) translation files.
- **Stale Key Pruning**: Automatically detect and remove translation keys that no longer exist in your base language.
- **Multiple Variations**: Generate 3 translation options per key and interactively choose the best one.
- **Forced Full Translation**: Option to re-translate all keys entirely.

## Installation

This package is installed locally via a path repository. It is already registered in `composer.json` under:

```json
"repositories": [
    {
        "type": "path",
        "url": "packages/*"
    }
]
```

## Configuration

AI provider, model, and API keys are managed by the Laravel AI SDK (`laravel/ai`). Publish and configure it in your host app:

```bash
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
```

Then set your provider credentials in `.env` (examples):

```env
# Laravel AI — pick one (or more) providers in config/ai.php
OPENAI_API_KEY=your_openai_api_key_here
ANTHROPIC_API_KEY=your_anthropic_api_key_here
GEMINI_API_KEY=your_google_gemini_api_key_here
```

Configure the default provider (and optional per-provider default models) in `config/ai.php`. Insta Translate uses those defaults unless you pass `--provider` / `--model`.

Package-specific settings:

```env
# Path to your language files (defaults to the laravel lang directory)
INSTA_TRANSLATE_LANG_PATH=./lang

# The base language to translate from (defaults to "en")
INSTA_TRANSLATE_DEFAULT_LANGUAGE=en
```

You can optionally publish the package configuration file:

```bash
php artisan vendor:publish --tag="insta-translate-config"
```

## Usage

### Generating Translations

```bash
php artisan translation:generate
```

#### Options

| Option | Description | Example |
|---|---|---|
| `--batch=` | Number of keys per API request. Defaults to `50`. | `--batch=100` |
| `--provider=` | Override the Laravel AI default provider. | `--provider=anthropic` |
| `--model=` | Override the provider default model (exact model id). | `--model=claude-sonnet-4-20250514` |
| `--lang=` | Target a single locale, creating it if it doesn't exist. | `--lang=nl` |
| `--key=` | Translate specific key(s). Can be used multiple times. | `--key="Welcome" --key="Goodbye"` |
| `--multiple` | Generate 3 translation variations per key and choose interactively. | `--key="foo" --multiple` |
| `--all` | Re-translate **all** keys, overwriting existing translations. | `--all` |
| `--context=` | Provide domain context for more accurate translations. | `--context="SaaS billing dashboard"` |
| `--php` | Process PHP array files (`lang/en/*.php`) instead of JSON. | `--php` |

### Pruning Stale Keys

Remove translation keys from target locales that no longer exist in the base language file:

```bash
# Preview what would be removed (safe)
php artisan translation:prune --dry-run

# Actually remove stale keys
php artisan translation:prune

# Prune a specific locale
php artisan translation:prune --lang=fr

# Prune PHP array files
php artisan translation:prune --php --dry-run
```

## Glossary

Create a `glossary.json` file in your `lang/` directory to protect brand names and define locale-specific translations:

```json
{
  "never_translate": ["InstaRequest", "Laravel", "Stripe", "API"],
  "specific_translations": {
    "Dashboard": {
      "hi": "डैशबोर्ड",
      "fr": "Tableau de bord"
    }
  }
}
```

- **`never_translate`**: Terms that must remain exactly as-is in every language (brand names, technical terms).
- **`specific_translations`**: Per-locale overrides for specific terms. These are injected into the AI prompt and also applied as post-processing replacements.

The glossary path defaults to `lang/glossary.json` and can be overridden via:

```env
INSTA_TRANSLATE_GLOSSARY_PATH=./lang/glossary.json
```

## How It Works

1. The command reads the base language file (e.g., `en.json` or `en/*.php`).
2. It iterates over all target locale files.
3. For each locale, it identifies keys that exist in the base file but are missing in the target (unless `--all` is passed).
4. Glossary terms and context are injected into the AI prompt.
5. Missing keys are batched and sent to the AI model via `laravel/ai`.
6. Glossary overrides are applied to the AI response.
7. Translations are merged, sorted alphabetically, and saved.
