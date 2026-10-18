import { lazy } from 'react';
import type { ComponentType, LazyExoticComponent } from 'react';

export type ModuleComponentKind = 'widgets' | 'profile' | 'header-actions';

type Registry = Record<string, () => Promise<unknown>>;
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyComponent = LazyExoticComponent<ComponentType<any>>;

// Ленивые компоненты запоминаются на реестр и ключ: иначе React ремонтирует их на каждый рендер.
const cache = new WeakMap<Registry, Map<string, AnyComponent>>();
const warned = new Set<string>();

/**
 * `<module>::<Name>` → `/vendor/ladcms/<module>/resources/js/<kind>/<Name>.tsx` в glob-реестре.
 * Нет файла — `null` (сервер уже отфильтровал такие компоненты; клиент защищается ещё раз, в dev — warn).
 */
export function resolveModuleComponent(
    kind: ModuleComponentKind,
    name: string,
    registry: Registry,
): AnyComponent | null {
    const [module, component] = name.split('::', 2);
    const key = `/vendor/ladcms/${module}/resources/js/${kind}/${component}.tsx`;
    const loader = component ? registry[key] : undefined;

    if (!loader) {
        if (import.meta.env.DEV && !warned.has(key)) {
            warned.add(key);
            console.warn(
                `Module component "${name}" not found (expected ${key})`,
            );
        }

        return null;
    }

    let perRegistry = cache.get(registry);
    if (!perRegistry) {
        perRegistry = new Map();
        cache.set(registry, perRegistry);
    }

    let resolved = perRegistry.get(key);
    if (!resolved) {
        resolved = lazy(() => loader() as Promise<{ default: ComponentType }>);
        perRegistry.set(key, resolved);
    }

    return resolved;
}
