<?php

declare(strict_types=1);

namespace Ladcms\Core\Admin;

use Ladcms\Core\Models\Module;
use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Module\ModuleServiceProvider;

/**
 * Строки страницы «Модули»: объединение реестра (код), его отказов и таблицы `modules` (БД).
 */
final readonly class ModulesOverview
{
    public function __construct(private ModuleRegistry $registry) {}

    /**
     * @return list<array{name: string, version: string|null, installed_version: string|null, is_enabled: bool, status: 'active'|'failed'|'disabled'|'not_installed', reason: string|null, needs_update: bool, is_core: bool, dependencies: list<string>}>
     */
    public function rows(): array
    {
        $failures = $this->registry->failures();
        $installed = Module::query()->get()->keyBy('name');

        $names = collect($this->registry->names())
            ->merge(array_keys($failures))
            ->merge($installed->keys())
            ->unique();

        $rows = $names->map(function (string $name) use ($failures, $installed): array {
            $provider = $this->registry->getRegistered($name);
            $row = $installed->get($name);
            $reason = ($failures[$name] ?? null)?->reason;

            $status = match (true) {
                ! $provider instanceof ModuleServiceProvider => 'not_installed',
                $row !== null && ! $row->is_enabled => 'disabled',
                $reason !== null => 'failed',
                $row === null => 'not_installed',
                default => 'active',
            };

            if (! $provider instanceof ModuleServiceProvider) {
                $reason = 'module code is not available';
            }

            $isCore = $name === 'core';
            if ($isCore) {
                $status = 'active';
                $reason = null;
            }

            return [
                'name' => $name,
                'version' => $provider?->version(),
                'installed_version' => $row?->version,
                'is_enabled' => $isCore || ($row !== null && $row->is_enabled),
                'status' => $status,
                'reason' => $reason,
                'needs_update' => $provider instanceof ModuleServiceProvider && $row !== null && $provider->version() !== $row->version,
                'is_core' => $isCore,
                'dependencies' => $provider?->dependencies() ?? [],
            ];
        });

        return $rows
            ->sort(fn (array $a, array $b): int => [! $a['is_core'], $a['name']] <=> [! $b['is_core'], $b['name']])
            ->values()
            ->all();
    }
}
