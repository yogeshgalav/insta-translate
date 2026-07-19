# InstaTranslate for Laravel

A lightweight, local Laravel package that leverages Claude and Gemini LLMs to automatically translate language strings for your application. It replaces traditional translation management platforms by using AI to directly sync and translate missing keys in your `lang/*.json` files.

## Features

- **Automated Translation**: Diffs your base locale (`en.json`) against target locales (`hi.json`, `fr.json`, etc.) and translates only the missing keys.
- **Batched Requests**: Chunks translations to prevent hitting context window limits of AI models.
- **Multiple Models Supported**: Use Anthropic's Claude (`claude-3-5-sonnet-20241022`) or Google's Gemini (`gemini-1.5-flash`).
- **No Heavy SDKs**: Uses Laravel's native `Http` facade.
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

Add the following environment variables to your `.env` file to configure InstaTranslate:

```env
# Set the default model (e.g., claude-3-5-sonnet-20241022, gemini-1.5-pro)
# "claude" and "gemini" are also accepted as shorthands for default models.
INSTA_TRANSLATE_MODEL=claude

# Path to your language files (defaults to the laravel lang directory)
INSTA_TRANSLATE_LANG_PATH=./lang

# The base language to translate from (defaults to "en")
INSTA_TRANSLATE_DEFAULT_LANGUAGE=en

# Add your API Keys
INSTA_TRANSLATE_CLAUDE_KEY=your_anthropic_api_key_here
INSTA_TRANSLATE_GEMINI_KEY=your_google_gemini_api_key_here
```

You can optionally publish the configuration file to customize the default model behavior:

```bash
php artisan vendor:publish --tag="insta-translate-config"
```

## Usage

To generate translations, run the artisan command:

```bash
php artisan translation:generate
```

### Options

| Option | Description | Example |
|---|---|---|
| `--batch=` | Sets the number of keys to translate per API request. Defaults to `50`. | `php artisan translation:generate --batch=100` |
| `--model=` | Overrides the default model configured in `.env`. Accepts shorthand (`claude`, `gemini`) or exact model versions (e.g., `gemini-1.5-pro`, `gemma-2b`, `claude-3-opus-20240229`). | `php artisan translation:generate --model=gemini-1.5-pro` |
| `--lang=` | Specifically targets a single language file, creating it if it doesn't exist. | `php artisan translation:generate --lang=nl` |
| `--key=` | Translates a specific key. You can pass this multiple times to translate several specific keys. Overrides the missing key check. | `php artisan translation:generate --key="Welcome to our app" --key="auth.failed"` |
| `--multiple` | Instructs the AI to generate 3 different translation variations for each key and interactively prompts you to select the best one. | `php artisan translation:generate --key="foo" --multiple` |
| `--all` | Forces the translation of **all** keys present in `en.json`, overwriting existing translations in the target locale files. | `php artisan translation:generate --all` |

### How It Works

1. The command reads the base language file (e.g., `en.json` by default).
2. It iterates over all other `.json` files in your `lang/` directory (e.g., `hi.json`, `fr.json`, `de.json`).
3. For each file, it identifies which keys exist in the base file but are missing in the target locale (unless `--all` is passed).
4. The missing keys are batched and sent to the selected AI model via a strict JSON prompt.
5. The translations are merged with the existing translations, sorted alphabetically by key, and saved back to the JSON file.
