<?php

declare(strict_types=1);

namespace Ladcms\Core\Ui\Concerns;

use InvalidArgumentException;
use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Module\ModuleServiceProvider;

/** Общее для реестров UI: предупреждения «раз на запрос», детерминированная сортировка, проверка файла компонента. */
trait UiRegistry
{
    /** @var array<string, true> */
    private array $warned = [];

    private function warnOnce(string $id, string $message): void
    {
        if (! isset($this->warned[$id])) {
            $this->warned[$id] = true;
            logger()->warning($message);
        }
    }

    /**
     * @param  array<array-key, mixed>  $entry
     * @param  list<string>  $fields
     *
     * @throws InvalidArgumentException
     */
    private function requireFields(array $entry, array $fields): void
    {
        foreach ($fields as $field) {
            if (! isset($entry[$field])) {
                throw new InvalidArgumentException("missing [{$field}]");
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function sortItems(array $items, string $keyField): array
    {
        usort($items, fn (array $a, array $b): int => [$a['order'], $a[$keyField]] <=> [$b['order'], $b[$keyField]]);

        return $items;
    }

    /** Файл `resources/js/<kind>/<Name>.tsx` модуля для компонента `<module>::<Name>`; иначе — предупреждение. */
    private function componentExists(ModuleRegistry $modules, string $component, string $kind): bool
    {
        [$name, $file] = array_pad(explode('::', $component, 2), 2, '');
        $module = $modules->get($name);

        if ($module instanceof ModuleServiceProvider
            && $file !== ''
            && ! str_contains($file, '/')
            && is_file($module->modulePath("resources/js/{$kind}/{$file}.tsx"))) {
            return true;
        }

        $this->warnOnce("component:{$component}", "UI component [{$component}] has no file under resources/js/{$kind}/; entry skipped.");

        return false;
    }
}
