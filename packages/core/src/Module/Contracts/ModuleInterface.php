<?php

declare(strict_types=1);

namespace Ladcms\Core\Module\Contracts;

interface ModuleInterface
{
    public function name(): string;

    public function version(): string;

    /** Composer-constraint на версию core, например "^1.0". */
    public function coreVersion(): string;

    /** @return list<string> имена обязательных модулей */
    public function dependencies(): array;

    /** @return list<string> */
    public function optionalDependencies(): array;

    /** @return array<string, string> npm-пакет => версия */
    public function npmDependencies(): array;

    public function install(): void;

    public function uninstall(): void;
}
