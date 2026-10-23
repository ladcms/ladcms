import { Card } from '@ladcms/core/components/ui/card';
import { Skeleton } from '@ladcms/core/components/ui/skeleton';
import { useComponentRegistry } from '@ladcms/core/inertia/component-registry';
import { resolveModuleComponent } from '@ladcms/core/inertia/resolve-component';
import type { LayoutWidget } from '@ladcms/core/types/layout';
import { ModuleSlot } from './module-slot';

/** Виджеты слота layout: ленивые компоненты модулей, каждый в `Card`, props виджета разворачиваются в компонент. */
export function WidgetList({ widgets }: { widgets: LayoutWidget[] }) {
    const registry = useComponentRegistry();

    return (
        <div className="flex flex-col gap-4">
            {widgets.map((widget) => {
                const Widget = resolveModuleComponent(
                    'widgets',
                    widget.component,
                    registry,
                );

                if (!Widget) {
                    return null;
                }

                return (
                    <Card
                        key={`${widget.module}:${widget.name}`}
                        className="gap-0 overflow-hidden py-0"
                    >
                        <ModuleSlot fallback={<Skeleton className="h-24" />}>
                            <Widget {...widget.props} />
                        </ModuleSlot>
                    </Card>
                );
            })}
        </div>
    );
}
