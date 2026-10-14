<?php

declare(strict_types=1);

namespace Ladcms\Core\Inertia;

use Illuminate\View\ViewFinderInterface;
use InvalidArgumentException;
use Ladcms\Core\Module\ModuleRegistry;

/**
 * Декоратор `inertia.view-finder` (Illuminate\View\FileViewFinder): имя `module::Path`
 * ищется в `<modulePath>/resources/js/pages/Path.tsx`, остальные имена — оригиналом.
 * Только `.tsx` — как и фронтовый резолвер (resources/js/inertia/resolve-page.ts).
 * Не найдено → InvalidArgumentException, как у FileViewFinder (Inertia превращает его в ComponentNotFoundException).
 */
class ModulePageFinder implements ViewFinderInterface
{
    /**
     * @param  list<string>  $extensions
     */
    public function __construct(
        private readonly ViewFinderInterface $inner,
        private readonly ModuleRegistry $registry,
        private readonly array $extensions = ['tsx'],
    ) {}

    /**
     * @param  string  $view
     */
    public function find($view): string
    {
        if (! str_contains($view, '::')) {
            return $this->inner->find($view);
        }

        [$module, $page] = explode('::', $view, 2);
        $provider = $this->registry->get($module)
            ?? throw new InvalidArgumentException("Inertia page [{$view}]: module [{$module}] is not registered.");

        foreach ($this->extensions as $ext) {
            $file = $provider->modulePath("resources/js/pages/{$page}.{$ext}");
            if (is_file($file)) {
                return $file;
            }
        }

        throw new InvalidArgumentException("Inertia page [{$view}] not found in module [{$module}].");
    }

    public function addLocation($location): void
    {
        $this->inner->addLocation($location);
    }

    /**
     * @param  string  $namespace
     * @param  string|list<string>  $hints
     */
    public function addNamespace($namespace, $hints): void
    {
        $this->inner->addNamespace($namespace, $hints);
    }

    /**
     * @param  string  $namespace
     * @param  string|list<string>  $hints
     */
    public function prependNamespace($namespace, $hints): void
    {
        $this->inner->prependNamespace($namespace, $hints);
    }

    /**
     * @param  string  $namespace
     * @param  string|list<string>  $hints
     */
    public function replaceNamespace($namespace, $hints): void
    {
        $this->inner->replaceNamespace($namespace, $hints);
    }

    public function addExtension($extension): void
    {
        $this->inner->addExtension($extension);
    }

    public function flush(): void
    {
        $this->inner->flush();
    }

    /**
     * Прочие публичные методы FileViewFinder (getPaths, exists и т.п.).
     *
     * @param  array<int, mixed>  $args
     */
    public function __call(string $method, array $args): mixed
    {
        return $this->inner->{$method}(...$args);
    }
}
