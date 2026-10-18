<?php

declare(strict_types=1);

namespace Ladcms\Core\Module;

use Composer\Semver\Semver;
use Illuminate\Support\Collection;
use Ladcms\Core\Models\Module;
use Throwable;
use UnexpectedValueException;

class ModuleRegistry
{
    /** @var array<string, ModuleServiceProvider> все зарегистрированные модули */
    private array $registered = [];

    /** @var array<string, ModuleServiceProvider> модули, прошедшие проверку */
    private array $modules = [];

    /** @var array<string, ModuleFailure> */
    private array $failures = [];

    /** @var array<string, string> модули, пропущенные при boot => причина пропуска */
    private array $skipped = [];

    /** @var array<string, string> модули, выключенные администратором => причина */
    private array $disabled = [];

    private bool $disabledLoaded = false;

    private bool $validated = false;

    public function __construct(private readonly string $coreVersion) {}

    public function register(ModuleServiceProvider $module): void
    {
        $this->registered[$module->name()] = $module;
        $this->validated = false;
        // Набор модулей изменился (в тестах — после появления строк в БД): выключенные читаем заново.
        $this->disabledLoaded = false;
    }

    /**
     * Проверяет версии и зависимости. Laravel регистрирует все провайдеры до boot любого из них,
     * поэтому к первому вызову (из boot модуля) набор модулей полон и порядок регистрации не важен.
     * Каждый прогон пересчитывает результат с нуля по текущему набору модулей.
     */
    public function validate(): void
    {
        if ($this->validated) {
            return;
        }

        $this->loadDisabledFromDatabase();

        $previous = $this->failures;
        $this->modules = $this->registered;
        $this->failures = [];

        foreach ($this->modules as $name => $module) {
            if ($name === 'core') {
                continue;
            }

            try {
                if (! Semver::satisfies($this->coreVersion, $module->coreVersion())) {
                    $this->fail($name, "requires core {$module->coreVersion()}, installed {$this->coreVersion}");
                }
            } catch (UnexpectedValueException $e) {
                // Битый constraint одного модуля не должен ронять весь сайт.
                $this->fail($name, "invalid core version constraint [{$module->coreVersion()}]: {$e->getMessage()}");
            }
        }

        // Выключенный администратором модуль недоступен — как непрошедший проверку (причина важнее «skipped at boot»).
        foreach ($this->disabled as $name => $reason) {
            if (isset($this->modules[$name])) {
                $this->fail($name, $reason);
            }
        }

        // Модуль, пропущенный при boot, не загрузил ресурсы — он недоступен, даже если теперь проверку проходит.
        foreach ($this->skipped as $name => $reason) {
            if (isset($this->modules[$name])) {
                $this->fail($name, "skipped at boot: {$reason}");
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

        foreach ($this->failures as $name => $failure) {
            if (! isset($this->disabled[$name]) && ($previous[$name] ?? null)?->reason !== $failure->reason) {
                logger()->warning("LADCMS module [{$name}] disabled: {$failure->reason}");
            }
        }

        $this->validated = true;
    }

    /** Помечает модуль, который не был загружен при boot из-за непройденной проверки. */
    public function markSkipped(string $name): void
    {
        $this->validate();

        $this->skipped[$name] ??= $this->failures[$name]->reason ?? 'unknown reason';
        $this->validated = false;
    }

    /** Помечает модуль выключенным администратором. `core` выключить нельзя. */
    public function markDisabled(string $name, string $reason = 'disabled by administrator'): void
    {
        if ($name === 'core') {
            return;
        }

        $this->disabled[$name] = $reason;
        $this->validated = false;
    }

    /**
     * Один раз за запрос читает `modules.is_enabled = false` из БД. Таблицы может не быть
     * (`ladcms:install` до миграций, тесты без БД) — тогда выключенных модулей нет.
     */
    private function loadDisabledFromDatabase(): void
    {
        if ($this->disabledLoaded) {
            return;
        }
        $this->disabledLoaded = true;

        // Fail open намеренно: нет таблицы, соединения или драйвера — значит, выключенных модулей нет,
        // и приложение (включая artisan-команды вроде config:clear) продолжает загружаться.
        try {
            $names = Module::query()->where('is_enabled', false)->pluck('name');
        } catch (Throwable) {
            return;
        }

        foreach ($names as $name) {
            if ($name !== 'core') {
                $this->disabled[(string) $name] = 'disabled by administrator';
            }
        }
    }

    private function fail(string $name, string $reason): void
    {
        unset($this->modules[$name]);
        $this->failures[$name] = new ModuleFailure($name, $reason);
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

    /** Провайдер с таким именем зарегистрирован — независимо от того, прошёл ли он проверку. */
    public function isRegistered(string $name): bool
    {
        return isset($this->registered[$name]);
    }

    /** Зарегистрированный провайдер, даже если модуль не прошёл проверку (для install/uninstall). */
    public function getRegistered(string $name): ?ModuleServiceProvider
    {
        return $this->registered[$name] ?? null;
    }

    /**
     * Валидные модули, которые зависят от $name.
     *
     * @return list<string>
     */
    public function dependents(string $name): array
    {
        return $this->all()
            ->filter(fn (ModuleServiceProvider $m) => in_array($name, $m->dependencies(), true))
            ->keys()
            ->values()
            ->all();
    }

    /** @return list<string> */
    public function names(): array
    {
        return $this->all()->keys()->values()->all();
    }

    /**
     * Зарегистрированные модули, выключенные администратором.
     *
     * @return list<string>
     */
    public function disabled(): array
    {
        $this->validate();

        return array_values(array_filter(
            array_keys($this->disabled),
            fn (string $name): bool => isset($this->registered[$name]),
        ));
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
