import { createContext, useContext } from 'react';
import type { ReactNode } from 'react';

export type ComponentRegistry = Record<string, () => Promise<unknown>>;

const ComponentRegistryContext = createContext<ComponentRegistry | null>(null);

/** Передаёт core glob-реестр компонентов модулей, объявленный в приложении (`import.meta.glob`). */
export function ComponentRegistryProvider({
    registry,
    children,
}: {
    registry: ComponentRegistry;
    children: ReactNode;
}) {
    return (
        <ComponentRegistryContext.Provider value={registry}>
            {children}
        </ComponentRegistryContext.Provider>
    );
}

export function useComponentRegistry(): ComponentRegistry {
    const registry = useContext(ComponentRegistryContext);
    if (registry === null) {
        throw new Error(
            'useComponentRegistry() must be used inside <ComponentRegistryProvider>',
        );
    }

    return registry;
}
