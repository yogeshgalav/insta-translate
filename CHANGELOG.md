# Release Notes

## [Unreleased](https://github.com/janakkapadia/insta-translate/compare/0.2.0...HEAD)

### Changed

- Removed package-owned LLM model management (`INSTA_TRANSLATE_MODEL` / `default_model` and model shorthand resolution). Translations now use the Laravel AI SDK default provider and model from `config/ai.php`, with optional `--provider` / `--model` passthrough overrides.

## [0.2.0](https://github.com/janakkapadia/insta-translate/compare/0.1.8...0.2.0) - 2026-07-20

### Added
- **Glossary & Brand Terms Protection**: Create a `glossary.json` to define terms that should never be translated and locale-specific overrides.
- **Context-Aware Translations**: New `--context` option to provide domain context (e.g., "SaaS billing dashboard") for more accurate translations.
- **PHP Array File Support**: New `--php` flag to translate `lang/en/*.php` array files alongside JSON files.
- **Stale Key Pruning**: New `translation:prune` command to remove orphaned keys from target locales, with `--dry-run` and `--php` support.

## [0.1.8](https://github.com/janakkapadia/insta-translate/compare/0.1.7...0.1.8) - 2026-07-20

### Fixed

- Fixed PHPStan type coverage issue with `callAi()` return type missing docblock.
- Fixed code style formatting via Laravel Pint.
- Removed leftover local scratchpad test files.

## [0.1.7](https://github.com/janakkapadia/insta-translate/compare/0.1.6...0.1.7) - 2026-07-20

### Changed

- Refactored `translation:generate` command to use the official `laravel/ai` SDK for prompting LLMs instead of manual HTTP requests.
- API keys are now automatically resolved by `laravel/ai` via standard environment variables (e.g., `ANTHROPIC_API_KEY`, `GEMINI_API_KEY`).
- Removed custom `retry_attempts` and `retry_delay_seconds` config as error handling is deferred to the AI SDK.

## [0.1.6](https://github.com/janakkapadia/insta-translate/compare/0.1.5...0.1.6) - 2026-07-20

### Added

- Added `.env` configuration support for retry delay via `INSTA_TRANSLATE_RETRY_DELAY_SECONDS` (defaults to 30 seconds).

## [0.1.5](https://github.com/janakkapadia/insta-translate/compare/0.1.4...0.1.5) - 2026-07-20

### Fixed

- Improved API retry logic to specifically handle `429 Too Many Requests` and `5xx Server Errors` explicitly via a custom closure.

## [0.1.4](https://github.com/janakkapadia/insta-translate/compare/0.1.3...0.1.4) - 2026-07-20

### Added

- Added automatic retry logic for failed API requests (defaults to 3 retries, configurable via `INSTA_TRANSLATE_RETRY_ATTEMPTS`).

## [0.1.3](https://github.com/janakkapadia/insta-translate/compare/0.1.2...0.1.3) - 2026-07-20

### Added

- Added `--multiple` flag to generate 3 translation options for each key and interactively prompt the user to select the best one.

## [0.1.2](https://github.com/janakkapadia/insta-translate/compare/0.1.1...0.1.2) - 2026-07-19

### Added

- Added interactive prompts when using `--key` to ask for the target language and confirm whether to overwrite existing translations.

## [0.1.1](https://github.com/janakkapadia/insta-translate/compare/0.1.0...0.1.1) - 2026-07-19

### Fixed

- Updated `homepage` URL in `composer.json` to the correct repository link.

## [0.1.0](https://github.com/janakkapadia/insta-translate/compare/0.0.5...0.1.0) - 2026-07-19

### Added

- Renamed package to `insta-translate`.
- Changed config keys to `insta-translate.*` and `INSTA_TRANSLATE_*`.
- Added support for `--key` specific translations.
- Fixed PHPStan type coverage issues and Laravel path configuration.
