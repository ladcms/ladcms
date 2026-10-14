<?php

namespace Ladcms\Core\Actions\Modules;

class SyncNpmDependencies
{
    /**
     * @param  array<string,string>  $dependencies
     * @return array<string,string> добавленные зависимости
     */
    public function handle(array $dependencies, string $packageJsonPath): array
    {
        if ($dependencies === [] || ! is_file($packageJsonPath)) {
            return [];
        }

        $json = json_decode((string) file_get_contents($packageJsonPath), true, flags: JSON_THROW_ON_ERROR);
        $existing = $json['dependencies'] ?? [];
        $added = array_diff_key($dependencies, $existing);

        if ($added === []) {
            return [];
        }

        $json['dependencies'] = $existing + $added;
        ksort($json['dependencies']);
        file_put_contents($packageJsonPath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        return $added;
    }
}
