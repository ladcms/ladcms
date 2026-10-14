<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Modules;

final readonly class InstallReport
{
    /**
     * @param  list<string>  $createdPermissions
     * @param  array<string,string>  $addedNpmDependencies
     */
    public function __construct(
        public string $module,
        public string $version,
        public array $createdPermissions,
        public array $addedNpmDependencies,
        public bool $npmChanged,
    ) {}
}
