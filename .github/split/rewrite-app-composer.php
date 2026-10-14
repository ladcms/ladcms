<?php

declare(strict_types=1);

// Usage: php rewrite-app-composer.php app/composer.json <ref>
// <ref> — версия тега без `v` (1.2.0) или имя ветки (main).
// Тег → ladcms/* требуются как ^<major.minor>; ветка → dev-main (тегов с такой версией может ещё не быть).
[$_, $file, $ref] = $argv;

$ref = ltrim($ref, 'v');
$constraint = preg_match('/^(\d+)\.(\d+)/', $ref, $m) === 1 ? "^{$m[1]}.{$m[2]}" : 'dev-main';

$json = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
unset($json['repositories']);
foreach (array_keys($json['require']) as $name) {
    if (str_starts_with($name, 'ladcms/')) {
        $json['require'][$name] = $constraint;
    }
}
file_put_contents($file, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
