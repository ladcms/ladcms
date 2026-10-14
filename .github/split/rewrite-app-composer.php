<?php
// Usage: php rewrite-app-composer.php app/composer.json 1.2.0
[$_, $file, $version] = $argv;
if (! preg_match('/^\d+\.\d+/', ltrim($version, 'v'))) {
    $version = '0.1.0';
}
$json = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
unset($json['repositories']);
[$major, $minor] = explode('.', ltrim($version, 'v'));
foreach ($json['require'] as $name => $constraint) {
    if (str_starts_with($name, 'ladcms/')) {
        $json['require'][$name] = "^{$major}.{$minor}";
    }
}
file_put_contents($file, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
