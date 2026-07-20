<?php

declare(strict_types=1);

namespace InstaRequest\InstaTranslate\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use InstaRequest\InstaTranslate\Support\PhpArrayFileHandler;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;

use function Laravel\Ai\agent;

class TranslationGenerateCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'translation:generate 
                            {--batch=50 : Number of keys to translate per API request}
                            {--provider= : Override the Laravel AI default provider (e.g. anthropic, openai, gemini).}
                            {--model= : Override the provider default model (exact model id).}
                            {--lang= : Specific language code to translate/create (e.g., fr, hi).}
                            {--key=* : Specific keys to translate (can be used multiple times). Overrides the missing check.}
                            {--multiple : Generate multiple translation options to choose from.}
                            {--all : Translate all keys, overwriting existing translations.}
                            {--context= : Provide context about what these strings are for (e.g. "SaaS billing dashboard").}
                            {--php : Process PHP array files (lang/en/*.php) instead of JSON.}';

    /**
     * The command description.
     */
    protected $description = 'Generate translations using the Laravel AI SDK.';

    /**
     * Glossary data loaded from glossary.json.
     *
     * @var array{never_translate?: list<string>, specific_translations?: array<string, array<string, string>>}
     */
    private array $glossary = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('Translation generation started.');

        $defaultLang = config('insta-translate.default_language', 'en');
        $langDir = rtrim(config('insta-translate.lang_path', base_path('lang')), '/');
        $phpMode = (bool) $this->option('php');

        $this->loadGlossary();

        if ($phpMode) {
            return $this->handlePhpFiles($langDir, $defaultLang);
        }

        return $this->handleJsonFiles($langDir, $defaultLang);
    }

    private function handleJsonFiles(string $langDir, string $defaultLang): int
    {
        $baseLangFile = $langDir.'/'.$defaultLang.'.json';

        if (! File::exists($baseLangFile)) {
            $this->error("Base language file {$defaultLang}.json does not exist.");

            return self::FAILURE;
        }

        $baseTranslations = json_decode(File::get($baseLangFile), true);

        if (! is_array($baseTranslations)) {
            $this->error("Invalid {$defaultLang}.json format.");

            return self::FAILURE;
        }

        $batchSize = max(1, (int) $this->option('batch'));
        $provider = $this->optionalStringOption('provider');
        $model = $this->optionalStringOption('model');
        $translateAll = (bool) $this->option('all');
        $specificKeys = (array) $this->option('key');
        $multiple = (bool) $this->option('multiple');
        $context = $this->optionalStringOption('context');

        $langOption = $this->optionalStringOption('lang');

        if (! empty($specificKeys) && ! $langOption) {
            $langOption = $this->ask('For which language code do you want to translate these keys? (Leave empty for all available languages)');

            if (empty($langOption)) {
                $langOption = null;
            }
        }

        if ($langOption) {
            $localeFile = str_ends_with($langOption, '.json') ? $langOption : $langOption.'.json';
            $locales = collect([$localeFile]);
        } else {
            $locales = collect(File::files($langDir))
                ->map(fn (SplFileInfo $file) => $file->getFilename())
                ->filter(fn (string $file) => str_ends_with($file, '.json') && $file !== $defaultLang.'.json' && ! str_starts_with($file, 'php_'));
        }

        foreach ($locales as $localeFile) {
            $localePath = $langDir.'/'.$localeFile;
            $targetLocale = str_replace('.json', '', $localeFile);
            $this->info("Processing locale: {$targetLocale}");

            $existingTranslations = File::exists($localePath) ? json_decode(File::get($localePath), true) ?? [] : [];

            $missingKeys = $this->resolveMissingKeys($baseTranslations, $existingTranslations, $specificKeys, $translateAll, $defaultLang, $targetLocale);

            if (empty($missingKeys)) {
                $this->line("No missing translations for {$targetLocale}.");

                continue;
            }

            $this->info('Found '.count($missingKeys)." missing keys for {$targetLocale}.");

            $chunks = array_chunk($missingKeys, $batchSize, true);

            foreach ($chunks as $index => $chunk) {
                $this->line('Translating batch '.($index + 1).' of '.count($chunks).'...');

                $translatedChunk = $this->translateChunk($chunk, $targetLocale, $defaultLang, $multiple, $context, $provider, $model);

                if ($translatedChunk) {
                    $translatedChunk = $this->applyGlossaryOverrides($translatedChunk, $targetLocale);

                    foreach ($translatedChunk as $key => $value) {
                        if ($multiple && is_array($value)) {
                            $options = array_map(fn (mixed $val) => (string) $val, $value);
                            $selected = $this->choice("Select translation for '{$key}' in {$targetLocale}", $options, 0);
                            $existingTranslations[$key] = $selected;
                        } else {
                            $existingTranslations[$key] = is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
                        }
                    }
                } else {
                    $this->error('Failed to translate batch '.($index + 1).'. Skipping.');
                }
            }

            ksort($existingTranslations);
            File::put($localePath, json_encode($existingTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}');
            $this->info("Saved {$localeFile}.");
        }

        $this->info('Translation generation complete.');

        return self::SUCCESS;
    }

    private function handlePhpFiles(string $langDir, string $defaultLang): int
    {
        $baseDir = $langDir.'/'.$defaultLang;

        if (! File::isDirectory($baseDir)) {
            $this->error("Base language directory {$defaultLang}/ does not exist.");

            return self::FAILURE;
        }

        $handler = new PhpArrayFileHandler;
        $batchSize = max(1, (int) $this->option('batch'));
        $provider = $this->optionalStringOption('provider');
        $model = $this->optionalStringOption('model');
        $translateAll = (bool) $this->option('all');
        $context = $this->optionalStringOption('context');

        $langOption = $this->optionalStringOption('lang');

        /** @var list<SplFileInfo> $baseFiles */
        $baseFiles = File::files($baseDir);

        if ($langOption) {
            $targetLocales = [$langOption];
        } else {
            $targetLocales = collect(File::directories($langDir))
                ->map(fn (string $dir) => basename($dir))
                ->filter(fn (string $dir) => $dir !== $defaultLang)
                ->values()
                ->all();
        }

        foreach ($baseFiles as $baseFile) {
            if ($baseFile->getExtension() !== 'php') {
                continue;
            }

            $filename = $baseFile->getFilename();
            $baseTranslations = $handler->read($baseFile->getPathname());
            $baseFlat = $handler->flattenWithDot($baseTranslations);

            $this->info("Processing PHP file: {$filename}");

            foreach ($targetLocales as $targetLocale) {
                $targetPath = $langDir.'/'.$targetLocale.'/'.$filename;
                $this->info("  Locale: {$targetLocale}");

                $existingFlat = File::exists($targetPath)
                    ? $handler->flattenWithDot($handler->read($targetPath))
                    : [];

                $missingKeys = [];

                foreach ($baseFlat as $key => $value) {
                    if ($translateAll || ! isset($existingFlat[$key])) {
                        $missingKeys[$key] = $value;
                    }
                }

                if (empty($missingKeys)) {
                    $this->line("  No missing translations for {$targetLocale}/{$filename}.");

                    continue;
                }

                $this->info('  Found '.count($missingKeys).' missing keys.');

                $chunks = array_chunk($missingKeys, $batchSize, true);

                foreach ($chunks as $index => $chunk) {
                    $this->line('  Translating batch '.($index + 1).' of '.count($chunks).'...');

                    $translatedChunk = $this->translateChunk($chunk, $targetLocale, $defaultLang, false, $context, $provider, $model);

                    if ($translatedChunk) {
                        $translatedChunk = $this->applyGlossaryOverrides($translatedChunk, $targetLocale);

                        foreach ($translatedChunk as $key => $translatedValue) {
                            $existingFlat[$key] = (string) $translatedValue;
                        }
                    } else {
                        $this->error('  Failed to translate batch '.($index + 1).'. Skipping.');
                    }
                }

                $rebuilt = $handler->unflattenDotNotation($existingFlat);
                ksort($rebuilt);
                $handler->write($targetPath, $rebuilt);
                $this->info("  Saved {$targetLocale}/{$filename}.");
            }
        }

        $this->info('Translation generation complete.');

        return self::SUCCESS;
    }

    /**
     * Resolve which keys need to be translated.
     *
     * @param  array<string, string>  $baseTranslations
     * @param  array<string, string>  $existingTranslations
     * @param  array<int, string>  $specificKeys
     * @return array<string, string>
     */
    private function resolveMissingKeys(array $baseTranslations, array $existingTranslations, array $specificKeys, bool $translateAll, string $defaultLang, string $targetLocale): array
    {
        $missingKeys = [];

        if (! empty($specificKeys)) {
            foreach ($specificKeys as $key) {
                if (isset($baseTranslations[$key])) {
                    if (isset($existingTranslations[$key]) && ! $translateAll) {
                        if (! $this->confirm("Key '{$key}' already exists in {$targetLocale}. Do you want to regenerate it?", false)) {
                            continue;
                        }
                    }
                    $missingKeys[$key] = $baseTranslations[$key];
                } else {
                    $this->warn("Key '{$key}' not found in {$defaultLang}.json. Skipping.");
                }
            }
        } else {
            foreach ($baseTranslations as $key => $value) {
                if ($translateAll || ! isset($existingTranslations[$key])) {
                    $missingKeys[$key] = $value;
                }
            }
        }

        return $missingKeys;
    }

    /**
     * @param  array<string, string>  $chunk
     * @return array<string, mixed>|null
     */
    private function translateChunk(
        array $chunk,
        string $targetLocale,
        string $defaultLang,
        bool $multiple = false,
        ?string $context = null,
        ?string $provider = null,
        ?string $model = null,
    ): ?array {
        $glossaryInstructions = $this->buildGlossaryPrompt($targetLocale);
        $contextLine = $context !== null ? "Context: {$context}\n" : '';

        if ($multiple) {
            $prompt = $contextLine.
                "Translate the following JSON key-value pairs from {$defaultLang} to {$targetLocale}. ".
                'Keep the keys exactly the same. Do not translate placeholders like :name or {value}. '.
                $glossaryInstructions.
                'Provide 3 distinct translation variations for each key. '.
                "Return ONLY a valid JSON object where keys are the same, and the value is a JSON array of 3 strings. No markdown formatting or other text.\n\n".
                json_encode($chunk, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $prompt = $contextLine.
                "Translate the following JSON key-value pairs from {$defaultLang} to {$targetLocale}. ".
                'Keep the keys exactly the same. Do not translate placeholders like :name or {value}. '.
                $glossaryInstructions.
                "Return ONLY a valid JSON object without markdown formatting or other text.\n\n".
                json_encode($chunk, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        return $this->callAi($prompt, $provider, $model);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function callAi(string $prompt, ?string $provider = null, ?string $model = null): ?array
    {
        try {
            $response = agent(
                instructions: 'You are a helpful translation assistant.',
            )->prompt($prompt, provider: $provider, model: $model);

            return $this->parseJsonResponse($response->text);
        } catch (Throwable $e) {
            $label = $provider ?? 'AI';
            $this->error(ucfirst($label).' API Error: '.$e->getMessage());

            return null;
        }
    }

    private function optionalStringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJsonResponse(?string $content): ?array
    {
        if (! $content) {
            return null;
        }

        // Remove possible markdown backticks
        $content = preg_replace('/```json\s*(.*?)\s*```/s', '$1', $content);
        $content = preg_replace('/```\s*(.*?)\s*```/s', '$1', $content);

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Failed to parse JSON response: '.json_last_error_msg());
            $this->line('Raw response: '.$content);

            return null;
        }

        return $decoded;
    }

    /**
     * Load glossary from the configured path.
     */
    private function loadGlossary(): void
    {
        $glossaryPath = config('insta-translate.glossary_path', base_path('lang/glossary.json'));

        if (! File::exists($glossaryPath)) {
            return;
        }

        $data = json_decode(File::get($glossaryPath), true);

        if (is_array($data)) {
            $this->glossary = $data;
            $this->line('Glossary loaded with '.count($data['never_translate'] ?? []).' protected term(s) and '.count($data['specific_translations'] ?? []).' override(s).');
        }
    }

    /**
     * Build prompt instructions from the glossary for a given target locale.
     */
    private function buildGlossaryPrompt(string $targetLocale): string
    {
        $parts = [];

        $neverTranslate = $this->glossary['never_translate'] ?? [];

        if ($neverTranslate !== []) {
            $terms = implode(', ', array_map(fn (string $t) => "\"$t\"", $neverTranslate));
            $parts[] = "IMPORTANT: The following terms are brand names or technical terms and must NEVER be translated. Keep them exactly as-is in the output: {$terms}. ";
        }

        $specificTranslations = $this->glossary['specific_translations'] ?? [];
        $localeOverrides = [];
        foreach ($specificTranslations as $term => $locales) {
            if (isset($locales[$targetLocale])) {
                $localeOverrides[] = "\"{$term}\" must be translated as \"{$locales[$targetLocale]}\"";
            }
        }

        if ($localeOverrides !== []) {
            $parts[] = 'Use these mandatory translations: '.implode('; ', $localeOverrides).'. ';
        }

        return implode('', $parts);
    }

    /**
     * Apply glossary-specific translation overrides after getting AI response.
     *
     * @param  array<string, mixed>  $translations
     * @return array<string, mixed>
     */
    private function applyGlossaryOverrides(array $translations, string $targetLocale): array
    {
        $specificTranslations = $this->glossary['specific_translations'] ?? [];

        foreach ($translations as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            foreach ($specificTranslations as $term => $locales) {
                if (isset($locales[$targetLocale]) && stripos($value, $term) !== false) {
                    // If the translated value contains a glossary term, replace it with the override
                    $translations[$key] = str_ireplace($term, $locales[$targetLocale], $value);
                }
            }
        }

        return $translations;
    }
}
