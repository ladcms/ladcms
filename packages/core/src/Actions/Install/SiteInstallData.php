<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Install;

final readonly class SiteInstallData
{
    public function __construct(
        public string $siteName,
        public string $language,
        public string $adminLogin,
        public string $adminName,
        public string $adminEmail,
        public string $adminPassword,
    ) {}
}
