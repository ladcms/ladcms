<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Modules;

use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use Ladcms\Core\Models\Module;
use Ladcms\Core\Models\User;
use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Support\Audit;

/** Включает или выключает установленный модуль; выключенный не загружается со следующего запроса. */
class ToggleModule
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    /**
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function handle(User $actor, string $name, bool $enabled): Module
    {
        if (! $actor->can('modules.manage')) {
            throw new AuthorizationException('You cannot manage modules.');
        }

        if ($name === 'core') {
            throw new InvalidArgumentException('The core module cannot be disabled.');
        }

        $module = Module::query()->where('name', $name)->first();

        if ($module === null) {
            throw new InvalidArgumentException($this->registry->isRegistered($name)
                ? "Module [{$name}] is not installed."
                : "Unknown module [{$name}].");
        }

        if (! $enabled) {
            $dependents = $this->registry->dependents($name);
            if ($dependents !== []) {
                throw new InvalidArgumentException(
                    "Cannot disable [{$name}]: required by [".implode('], [', $dependents).'].',
                );
            }
        }

        if ($enabled) {
            $disabled = array_values(array_intersect($this->registry->getRegistered($name)?->dependencies() ?? [], $this->registry->disabled()));
            if ($disabled !== []) {
                throw new InvalidArgumentException(
                    "Cannot enable [{$name}]: requires disabled module [".implode('], [', $disabled).'].',
                );
            }
        }

        if ($module->is_enabled === $enabled) {
            return $module;
        }

        $module->update(['is_enabled' => $enabled]);

        Audit::log('module.toggled', $actor, $module, ['module' => $name, 'enabled' => $enabled]);

        return $module;
    }
}
