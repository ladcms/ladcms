<?php

declare(strict_types=1);

namespace Ladcms\Core\Settings;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Ladcms\Core\Module\Contracts\HasSettings;
use Ladcms\Core\Module\ModuleRegistry;
use Spatie\LaravelSettings\Settings;

/**
 * Группы настроек всех модулей с HasSettings: ключ группы ↔ класс ↔ владеющий модуль.
 *
 * @phpstan-type SettingsGroup array{key: string, label: string, class: class-string<Settings>, module: string}
 */
class SettingsGroups
{
    public function __construct(private readonly ModuleRegistry $modules) {}

    /** @return list<SettingsGroup> */
    public function all(): array
    {
        $groups = [];
        foreach ($this->modules->implementing(HasSettings::class) as $name => $module) {
            foreach ($module->settings() as $class) {
                $key = $class::group();
                $titleKey = "{$name}::settings.{$key}.title";
                $groups[] = [
                    'key' => $key,
                    'label' => Lang::has($titleKey) ? (string) __($titleKey) : Str::ucfirst($key),
                    'class' => $class,
                    'module' => $name,
                ];
            }
        }

        return $groups;
    }

    /** @return SettingsGroup|null */
    public function resolve(string $group): ?array
    {
        return array_find($this->all(), fn (array $item): bool => $item['key'] === $group);
    }

    /** @param  class-string<Settings>  $class */
    public function moduleOf(string $class): ?string
    {
        return array_find($this->all(), fn (array $item): bool => $item['class'] === $class)['module'] ?? null;
    }
}
