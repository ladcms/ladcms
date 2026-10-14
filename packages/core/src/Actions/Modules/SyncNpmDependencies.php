<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Modules;

use stdClass;

class SyncNpmDependencies
{
    /**
     * Дописывает недостающие зависимости в package.json, не перезаписывая существующие.
     *
     * Файл декодируется в объекты, чтобы round-trip был без потерь: пустые `{}` не превращаются в `[]`,
     * юникод не экранируется, отступ — 2 пробела, как пишет npm.
     *
     * @param  array<string,string>  $dependencies
     * @return array<string,string> добавленные зависимости
     */
    public function handle(array $dependencies, string $packageJsonPath): array
    {
        if ($dependencies === [] || ! is_file($packageJsonPath)) {
            return [];
        }

        $json = json_decode((string) file_get_contents($packageJsonPath), false, 512, JSON_THROW_ON_ERROR);
        if (! $json instanceof stdClass) {
            return [];
        }

        $existing = isset($json->dependencies) && $json->dependencies instanceof stdClass
            ? (array) $json->dependencies
            : [];
        $added = array_diff_key($dependencies, $existing);

        if ($added === []) {
            return [];
        }

        $merged = $existing + $added;
        ksort($merged);
        $json->dependencies = (object) $merged;

        $encoded = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $encoded = (string) preg_replace_callback('/^(?: {4})+/m', fn (array $m) => str_repeat('  ', intdiv(strlen($m[0]), 4)), $encoded);

        file_put_contents($packageJsonPath, $encoded."\n");

        return $added;
    }
}
