import { Component, Suspense } from 'react';
import type { ReactNode } from 'react';

class SlotBoundary extends Component<
    { children: ReactNode },
    { failed: boolean }
> {
    state = { failed: false };

    static getDerivedStateFromError() {
        return { failed: true };
    }

    componentDidCatch(error: unknown) {
        if (import.meta.env.DEV) {
            console.warn('Module component crashed', error);
        }
    }

    render() {
        return this.state.failed ? null : this.props.children;
    }
}

/** Изолирует компонент модуля: ленивая загрузка в `Suspense`, падение рендера не ломает страницу. */
export function ModuleSlot({
    fallback,
    children,
}: {
    fallback: ReactNode;
    children: ReactNode;
}) {
    return (
        <SlotBoundary>
            <Suspense fallback={fallback}>{children}</Suspense>
        </SlotBoundary>
    );
}
