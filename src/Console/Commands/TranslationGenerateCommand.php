<?php

declare(strict_types=1);

namespace InstaRequest\AiTranslator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class TranslationGenerateCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'translation:generate 
                            {--batch=50 : Number of keys to translate per API request}
                            {--model= : Which model to use (e.g. claude-3-opus-20240229, gemini-1.5-pro). Overrides env config.}
                            {--lang= : Specific language code to translate/create (e.g., fr, hi).}
                            {--all : Translate all keys, overwriting existing translations.}';

    /**
     * The command description.
     */
    protected $description = 'Generate translations using Anthropic or Google AI models.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('Translation generation started.');
        
        $langDir = rtrim(config('ai-translator.lang_path', base_path('lang')), '/');
        $baseLangFile = $langDir . '/en.json';
        
        if (!File::exists($baseLangFile)) {
            $this->error('Base language file en.json does not exist.');
            return self::FAILURE;
        }

        $baseTranslations = json_decode(File::get($baseLangFile), true);
        
        if (!is_array($baseTranslations)) {
            $this->error('Invalid en.json format.');
            return self::FAILURE;
        }

        $batchSize = (int) $this->option('batch');
        $model = $this->option('model') ?: config('ai-translator.default_model', 'claude');
        $translateAll = $this->option('all');

        $langOption = $this->option('lang');

        if ($langOption) {
            $localeFile = str_ends_with($langOption, '.json') ? $langOption : $langOption . '.json';
            $locales = collect([$localeFile]);
        } else {
            $locales = collect(File::files($langDir))
                ->map(fn ($file) => $file->getFilename())
                ->filter(fn ($file) => str_ends_with($file, '.json') && $file !== 'en.json');
        }

        foreach ($locales as $localeFile) {
            $localePath = $langDir . '/' . $localeFile;
            $targetLocale = str_replace('.json', '', $localeFile);
            $this->info("Processing locale: {$targetLocale}");
            
            $existingTranslations = File::exists($localePath) ? json_decode(File::get($localePath), true) ?? [] : [];
            
            $missingKeys = [];
            foreach ($baseTranslations as $key => $value) {
                if ($translateAll || !isset($existingTranslations[$key])) {
                    $missingKeys[$key] = $value;
                }
            }

            if (empty($missingKeys)) {
                $this->line("No missing translations for {$targetLocale}.");
                continue;
            }

            $this->info("Found " . count($missingKeys) . " missing keys for {$targetLocale}.");

            $chunks = array_chunk($missingKeys, $batchSize, true);
            
            foreach ($chunks as $index => $chunk) {
                $this->line("Translating batch " . ($index + 1) . " of " . count($chunks) . "...");
                
                $translatedChunk = $this->translateChunk($chunk, $targetLocale, $model);
                
                if ($translatedChunk) {
                    $existingTranslations = array_merge($existingTranslations, $translatedChunk);
                } else {
                    $this->error("Failed to translate batch " . ($index + 1) . ". Skipping.");
                }
            }

            // Save the updated translations, sorted by key for consistency
            ksort($existingTranslations);
            File::put($localePath, json_encode($existingTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("Saved {$localeFile}.");
        }

        $this->info('Translation generation complete.');
        return self::SUCCESS;
    }

    private function translateChunk(array $chunk, string $targetLocale, string $model): ?array
    {
        $prompt = "Translate the following JSON key-value pairs from English to {$targetLocale}. " .
            "Keep the keys exactly the same. Do not translate placeholders like :name or {value}. " .
            "Return ONLY a valid JSON object without markdown formatting or other text.\n\n" .
            json_encode($chunk, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $actualModel = $this->resolveModelName($model);

        if (str_starts_with($actualModel, 'claude')) {
            return $this->callClaude($prompt, $actualModel);
        } elseif (str_starts_with($actualModel, 'gemini') || str_starts_with($actualModel, 'gemma')) {
            return $this->callGemini($prompt, $actualModel);
        }

        $this->error("Unknown or unsupported model prefix: {$actualModel}");
        return null;
    }

    private function resolveModelName(string $model): string
    {
        if ($model === 'claude') {
            return 'claude-3-5-sonnet-20241022';
        }
        
        if ($model === 'gemini') {
            return 'gemini-1.5-flash';
        }
        
        return $model;
    }

    private function callClaude(string $prompt, string $model): ?array
    {
        $apiKey = config('ai-translator.claude_key');
        
        if (empty($apiKey)) {
            $this->error('Claude API key is missing.');
            return null;
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => $model,
            'max_tokens' => 4096,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ]
        ]);

        if (!$response->successful()) {
            $this->error("Claude API Error: " . $response->body());
            return null;
        }

        $content = $response->json('content.0.text');
        
        return $this->parseJsonResponse($content);
    }

    private function callGemini(string $prompt, string $model): ?array
    {
        $apiKey = config('ai-translator.gemini_key');
        
        if (empty($apiKey)) {
            $this->error('Gemini API key is missing.');
            return null;
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
            ]
        ]);

        if (!$response->successful()) {
            $this->error("Gemini API Error: " . $response->body());
            return null;
        }

        $content = $response->json('candidates.0.content.parts.0.text');
        
        return $this->parseJsonResponse($content);
    }

    private function parseJsonResponse(?string $content): ?array
    {
        if (!$content) {
            return null;
        }

        // Remove possible markdown backticks
        $content = preg_replace('/```json\s*(.*?)\s*```/s', '$1', $content);
        $content = preg_replace('/```\s*(.*?)\s*```/s', '$1', $content);

        $decoded = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Failed to parse JSON response: ' . json_last_error_msg());
            $this->line('Raw response: ' . $content);
            return null;
        }

        return $decoded;
    }
}
