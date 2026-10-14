<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/packages'])
    ->withSkip([__DIR__.'/packages/*/vendor'])
    ->withPhpSets(php84: true)
    ->withPreparedSets(deadCode: true, codeQuality: true)
    // Каждый PHP-файл пакетов — strict_types (не только «безопасные», как у SafeDeclareStrictTypesRector).
    ->withRules([DeclareStrictTypesRector::class]);
