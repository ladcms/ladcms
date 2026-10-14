<?php

declare(strict_types=1);

namespace Ladcms\Core\Module\Concerns;

use Ladcms\Core\Module\Contracts\HasSettings;

trait RegistersSettings
{
    protected function registerModuleSettings(): void
    {
        if (! $this instanceof HasSettings) {
            return;
        }

        foreach ($this->settings() as $class) {
            // spatie кэширует список классов статически при register() своего провайдера,
            // поэтому биндим сами — порядок провайдеров не важен.
            $this->app->scoped($class, fn () => new $class);
            config(['settings.settings' => array_values(array_unique([...config('settings.settings', []), $class]))]);
        }
    }
}
