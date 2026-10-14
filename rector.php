<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/packages'])
    ->withSkip([__DIR__.'/packages/*/vendor'])
    ->withPhpSets(php84: true)
    ->withPreparedSets(deadCode: true, codeQuality: true);
