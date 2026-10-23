import { Link, usePage } from '@inertiajs/react';
import { Skeleton } from '@ladcms/core/components/ui/skeleton';
import { useComponentRegistry } from '@ladcms/core/inertia/component-registry';
import { resolveModuleComponent } from '@ladcms/core/inertia/resolve-component';
import { resolveIcon } from '@ladcms/core/lib/icons';
import { useT } from '@ladcms/core/lib/i18n';
import { cn } from '@ladcms/core/lib/utils';
import type { LayoutShared } from '@ladcms/core/types/layout';
import { ModuleSlot } from './module-slot';
import { SearchBox } from './search-box';
import { UserMenu } from './user-menu';
import { home } from '@/routes';

function HeaderActions({
    actions,
}: {
    actions: LayoutShared['header_actions'];
}) {
    const registry = useComponentRegistry();

    return (
        <>
            {actions.map((action) => {
                const Action = resolveModuleComponent(
                    'header-actions',
                    action.component,
                    registry,
                );

                if (!Action) {
                    return null;
                }

                return (
                    <ModuleSlot
                        key={action.key}
                        fallback={<Skeleton className="size-9 rounded-full" />}
                    >
                        <Action />
                    </ModuleSlot>
                );
            })}
        </>
    );
}

export function SiteHeader() {
    const t = useT();
    const { layout } = usePage<{ layout?: LayoutShared | null }>().props;
    const site = layout?.site;
    const navigation = layout?.navigation ?? [];

    return (
        <header className="sticky top-0 z-30 border-b border-border bg-card/90 backdrop-blur">
            <div className="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 gap-y-2 px-4 py-2 sm:flex-nowrap">
                <Link
                    href={home()}
                    className="flex min-w-0 items-center gap-2 rounded-md font-semibold focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    {site?.logo_url && (
                        <img
                            src={site.logo_url}
                            alt=""
                            className="size-8 rounded-lg object-cover"
                        />
                    )}
                    <span className="truncate text-lg">{site?.name}</span>
                </Link>

                <nav
                    aria-label={t('core::public.header.main_nav')}
                    className="hidden min-w-0 [scrollbar-width:none] items-center gap-1 overflow-x-auto overflow-y-hidden lg:flex [&::-webkit-scrollbar]:hidden"
                >
                    {navigation.map((item) => {
                        const Icon = resolveIcon(item.icon);

                        return (
                            <Link
                                key={item.key}
                                href={item.url}
                                aria-current={item.active ? 'page' : undefined}
                                className={cn(
                                    'flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    item.active &&
                                        'bg-accent text-accent-foreground',
                                )}
                            >
                                <Icon aria-hidden className="size-4" />
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>

                <div className="ml-auto flex items-center gap-1 sm:order-last sm:ml-0">
                    <HeaderActions actions={layout?.header_actions ?? []} />
                    <UserMenu />
                </div>

                <SearchBox className="order-last w-full sm:order-none sm:ml-auto sm:w-64" />
            </div>
        </header>
    );
}
