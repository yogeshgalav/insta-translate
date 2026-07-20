<?php

declare(strict_types=1);

namespace InstaRequest\InstaTranslate\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use InstaRequest\InstaTranslate\Support\PhpArrayFileHandler;
use Symfony\Component\Finder\SplFileInfo;

class TranslationPruneCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'translation:prune
                            {--lang= : Prune a specific locale only}
                            {--dry-run : Show what would be removed without actually removing}
                            {--php : Prune PHP array files instead of JSON}';

    /**
     * The command description.
     */
    protected $description = 'Remove stale translation keys that no longer exist in the base language file.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $defaultLang = config('insta-translate.default_language', 'en');
        $langDir = rtrim(config('insta-translate.lang_path', base_path('lang')), '/');
        $dryRun = (bool) $this->option('dry-run');
        $phpMode = (bool) $this->option('php');
        $langOption = is_string($this->option('lang')) ? $this->option('lang') : null;

        if ($phpMode) {
            return $this->prunePhpFiles($langDir, $defaultLang, $langOption, $dryRun);
        }

        return $this->pruneJsonFiles($langDir, $defaultLang, $langOption, $dryRun);
    }

    private function pruneJsonFiles(string $langDir, string $defaultLang, ?string $langOption, bool $dryRun): int
    {
        $baseLangFile = $langDir.'/'.$defaultLang.'.json';

        if (! File::exists($baseLangFile)) {
            $this->error("Base language file {$defaultLang}.json does not exist.");

            return self::FAILURE;
        }

        $baseKeys = array_keys(json_decode(File::get($baseLangFile), true) ?? []);

        if ($langOption) {
            $localeFile = str_ends_with($langOption, '.json') ? $langOption : $langOption.'.json';
            $locales = collect([$localeFile]);
        } else {
            $locales = collect(File::files($langDir))
                ->map(fn (SplFileInfo $file) => $file->getFilename())
                ->filter(fn (string $file) => str_ends_with($file, '.json') && $file !== $defaultLang.'.json' && ! str_starts_with($file, 'php_'));
        }

        $totalPruned = 0;

        foreach ($locales as $localeFile) {
            $localePath = $langDir.'/'.$localeFile;
            $targetLocale = str_replace('.json', '', $localeFile);

            if (! File::exists($localePath)) {
                $this->warn("Skipping {$targetLocale}: file does not exist.");

                continue;
            }

            /** @var array<string, string> $translations */
            $translations = json_decode(File::get($localePath), true) ?? [];
            $staleKeys = array_diff(array_keys($translations), $baseKeys);

            if ($staleKeys === []) {
                $this->line("No stale keys found in {$targetLocale}.");

                continue;
            }

            $this->info(count($staleKeys)." stale key(s) found in {$targetLocale}:");

            foreach ($staleKeys as $key) {
                $this->line("  - {$key}");
            }

            if (! $dryRun) {
                foreach ($staleKeys as $key) {
                    unset($translations[$key]);
                }

                ksort($translations);
                File::put($localePath, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}');
                $this->info("Pruned {$targetLocale}.json.");
            }

            $totalPruned += count($staleKeys);
        }

        if ($dryRun && $totalPruned > 0) {
            $this->warn("Dry run complete. {$totalPruned} stale key(s) would be removed. Run without --dry-run to apply.");
        } elseif ($totalPruned > 0) {
            $this->info("Pruning complete. {$totalPruned} stale key(s) removed.");
        } else {
            $this->info('No stale keys found in any locale.');
        }

        return self::SUCCESS;
    }

    private function prunePhpFiles(string $langDir, string $defaultLang, ?string $langOption, bool $dryRun): int
    {
        $baseDir = $langDir.'/'.$defaultLang;

        if (! File::isDirectory($baseDir)) {
            $this->error("Base language directory {$defaultLang}/ does not exist.");

            return self::FAILURE;
        }

        $handler = new PhpArrayFileHandler;

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

        $totalPruned = 0;

        foreach ($baseFiles as $baseFile) {
            if ($baseFile->getExtension() !== 'php') {
                continue;
            }

            $filename = $baseFile->getFilename();
            $baseTranslations = $handler->read($baseFile->getPathname());
            $baseKeys = array_keys($handler->flattenWithDot($baseTranslations));

            foreach ($targetLocales as $targetLocale) {
                $targetPath = $langDir.'/'.$targetLocale.'/'.$filename;

                if (! File::exists($targetPath)) {
                    continue;
                }

                $targetTranslations = $handler->read($targetPath);
                $targetFlat = $handler->flattenWithDot($targetTranslations);
                $staleKeys = array_diff(array_keys($targetFlat), $baseKeys);

                if ($staleKeys === []) {
                    continue;
                }

                $this->info(count($staleKeys)." stale key(s) found in {$targetLocale}/{$filename}:");

                foreach ($staleKeys as $key) {
                    $this->line("  - {$key}");
                }

                if (! $dryRun) {
                    foreach ($staleKeys as $key) {
                        unset($targetFlat[$key]);
                    }

                    $rebuilt = $handler->unflattenDotNotation($targetFlat);
                    ksort($rebuilt);
                    $handler->write($targetPath, $rebuilt);
                    $this->info("Pruned {$targetLocale}/{$filename}.");
                }

                $totalPruned += count($staleKeys);
            }
        }

        if ($dryRun && $totalPruned > 0) {
            $this->warn("Dry run complete. {$totalPruned} stale key(s) would be removed. Run without --dry-run to apply.");
        } elseif ($totalPruned > 0) {
            $this->info("Pruning complete. {$totalPruned} stale key(s) removed.");
        } else {
            $this->info('No stale keys found in any locale.');
        }

        return self::SUCCESS;
    }
}
