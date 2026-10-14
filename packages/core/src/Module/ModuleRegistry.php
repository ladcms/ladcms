<?php

namespace Ladcms\Core\Module;

use Composer\Semver\Semver;
use Illuminate\Support\Collection;

class ModuleRegistry
{
    /** @var array<string, ModuleServiceProvider> */
    private array $modules = [];

    /** @var array<string, ModuleFailure> */
    private array $failures = [];

    private bool $validated = false;

    public function __construct(private readonly string $coreVersion) {}

    public function register(ModuleServiceProvider $module): void
    {
        $this->modules[$module->name()] = $module;
        $this->validated = false;
    }

    /**
     * Проверяет версии и зависимости. Вызывается после boot всех провайдеров,
     * чтобы порядок регистрации не влиял на результат.
     */
    public function validate(): void
    {
        if ($this->validated) {
            return;
        }

        foreach ($this->modules as $name => $module) {
            if ($name === 'core') {
                continue;
            }

            if (! Semver::satisfies($this->coreVersion, $module->coreVersion())) {
                $this->fail($name, "requires core {$module->coreVersion()}, installed {$this->coreVersion}");
            }
        }

        // Зависимости проверяем отдельным проходом, когда известно, кто уже отвалился.
        do {
            $changed = false;
            foreach ($this->modules as $name => $module) {
                foreach ($module->dependencies() as $dependency) {
                    if (! isset($this->modules[$dependency])) {
                        $this->fail($name, "requires module [{$dependency}] which is not installed or failed");
                        $changed = true;
                        break;
                    }
                }
            }
        } while ($changed);

        $this->validated = true;
    }

    private function fail(string $name, string $reason): void
    {
        unset($this->modules[$name]);
        $this->failures[$name] = new ModuleFailure($name, $reason);
        logger()->warning("LADCMS module [{$name}] disabled: {$reason}");
    }

    /** @return Collection<string, ModuleServiceProvider> */
    public function all(): Collection
    {
        $this->validate();

        return collect($this->modules);
    }

    public function has(string $name): bool
    {
        return $this->all()->has($name);
    }

    public function get(string $name): ?ModuleServiceProvider
    {
        return $this->all()->get($name);
    }

    /** @return list<string> */
    public function names(): array
    {
        return $this->all()->keys()->values()->all();
    }

    /** @return array<string, ModuleFailure> */
    public function failures(): array
    {
        $this->validate();

        return $this->failures;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $interface
     * @return Collection<string, ModuleServiceProvider&T>
     */
    public function implementing(string $interface): Collection
    {
        return $this->all()->filter(fn (ModuleServiceProvider $m) => $m instanceof $interface);
    }
}
