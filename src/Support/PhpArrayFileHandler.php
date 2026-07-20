<?php

declare(strict_types=1);

namespace InstaRequest\InstaTranslate\Support;

use Illuminate\Support\Facades\File;

class PhpArrayFileHandler
{
    /**
     * Read a PHP array file and return its contents.
     *
     * @return array<string, mixed>
     */
    public function read(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        $data = include $path;

        return is_array($data) ? $data : [];
    }

    /**
     * Write translations to a PHP array file with short array syntax.
     *
     * @param  array<string, mixed>  $translations
     */
    public function write(string $path, array $translations): void
    {
        $export = $this->varExportShort($translations, 1);
        $content = "<?php\n\nreturn {$export};\n";

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $content);
    }

    /**
     * Flatten a nested array into dot-notation keys.
     *
     * @param  array<string, mixed>  $array
     * @return array<string, string>
     */
    public function flattenWithDot(array $array, string $prefix = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix !== '' ? $prefix.'.'.$key : (string) $key;

            if (is_array($value) && $value !== []) {
                $results = array_merge($results, $this->flattenWithDot($value, $newKey));
            } else {
                $results[$newKey] = is_string($value) ? $value : (string) $value;
            }
        }

        return $results;
    }

    /**
     * Unflatten dot-notation keys back into a nested array.
     *
     * @param  array<string, mixed>  $array
     * @return array<string, mixed>
     */
    public function unflattenDotNotation(array $array): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            $keys = explode('.', (string) $key);
            $current = &$results;

            foreach ($keys as $i => $segment) {
                if ($i === count($keys) - 1) {
                    $current[$segment] = $value;
                } else {
                    if (! isset($current[$segment])) {
                        $current[$segment] = [];
                    }
                    $current = &$current[$segment];
                }
            }

            unset($current);
        }

        return $results;
    }

    /**
     * Export an array using short array syntax with proper indentation.
     *
     * @param  array<int|string, mixed>  $array
     */
    private function varExportShort(array $array, int $indentLevel = 0): string
    {
        $indent = str_repeat('    ', $indentLevel);
        $innerIndent = str_repeat('    ', $indentLevel + 1);
        $lines = [];

        foreach ($array as $key => $value) {
            $exportedKey = is_int($key) ? (string) $key : "'".addslashes((string) $key)."'";

            if (is_array($value)) {
                $exportedValue = $this->varExportShort($value, $indentLevel + 1);
            } elseif (is_string($value)) {
                $exportedValue = "'".addslashes($value)."'";
            } elseif (is_bool($value)) {
                $exportedValue = $value ? 'true' : 'false';
            } elseif ($value === null) {
                $exportedValue = 'null';
            } else {
                $exportedValue = (string) $value;
            }

            $lines[] = "{$innerIndent}{$exportedKey} => {$exportedValue},";
        }

        if ($lines === []) {
            return '[]';
        }

        return "[\n".implode("\n", $lines)."\n{$indent}]";
    }
}
