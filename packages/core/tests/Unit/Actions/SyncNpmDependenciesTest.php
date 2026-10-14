<?php

declare(strict_types=1);

use Ladcms\Core\Actions\Modules\SyncNpmDependencies;

it('adds missing npm dependencies without overwriting existing ones', function () {
    $path = tempnam(sys_get_temp_dir(), 'pkg');
    file_put_contents($path, json_encode(['name' => 'app', 'dependencies' => ['react' => '^19.0.0']], JSON_PRETTY_PRINT));

    $added = (new SyncNpmDependencies)->handle(['left-pad' => '^1.3', 'react' => '^18.0.0'], $path);

    $json = json_decode(file_get_contents($path), true);
    expect($added)->toBe(['left-pad' => '^1.3'])
        ->and($json['dependencies'])->toBe(['left-pad' => '^1.3', 'react' => '^19.0.0']);

    expect((new SyncNpmDependencies)->handle(['left-pad' => '^1.3'], $path))->toBe([]);
});

it('keeps package.json formatting lossless: empty objects, unicode, 2-space indent', function () {
    $path = tempnam(sys_get_temp_dir(), 'pkg');
    file_put_contents($path, "{\n  \"name\": \"app\",\n  \"description\": \"Модуль\",\n  \"scripts\": {},\n  \"dependencies\": {\n    \"react\": \"^19.0.0\"\n  }\n}\n");

    $added = (new SyncNpmDependencies)->handle(['left-pad' => '^1.3'], $path);

    $raw = file_get_contents($path);
    expect($added)->toBe(['left-pad' => '^1.3'])
        ->and($raw)->toContain('"scripts": {}')
        ->and($raw)->toContain('"description": "Модуль"')
        ->and($raw)->not->toContain('\u')
        ->and($raw)->toContain("\n  \"dependencies\": {")
        ->and($raw)->toContain("\n    \"left-pad\": \"^1.3\",\n    \"react\": \"^19.0.0\"\n  }")
        ->and($raw)->toEndWith("}\n");
});
