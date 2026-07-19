# Release Notes

## [Unreleased](https://github.com/janakkapadia/insta-translate/compare/0.1.6...HEAD)

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
