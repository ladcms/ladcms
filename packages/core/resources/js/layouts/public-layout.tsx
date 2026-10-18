import { Head, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { MobileTabBar } from '@ladcms/core/components/public/mobile-tab-bar';
import { ReadonlyBanner } from '@ladcms/core/components/public/readonly-banner';
import { SidebarWidgets } from '@ladcms/core/components/public/sidebar-widgets';
import { SiteFooter } from '@ladcms/core/components/public/site-footer';
import { SiteHeader } from '@ladcms/core/components/public/site-header';
import { Button } from '@ladcms/core/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@ladcms/core/components/ui/collapsible';
import { useT } from '@ladcms/core/lib/i18n';
import type { CurrentUser } from '@ladcms/core/types/admin';
import type { LayoutShared } from '@ladcms/core/types/layout';

type PublicLayoutProps = {
    children: ReactNode;
    title?: string;
};

export default function PublicLayout({ children, title }: PublicLayoutProps) {
    const t = useT();
    const { auth, layout } = usePage<{
        auth: { user: CurrentUser | null };
        layout?: LayoutShared | null;
    }>().props;
    const sidebar = layout?.widgets.sidebar ?? [];

    return (
        <div className="flex min-h-svh flex-col bg-background text-foreground">
            {title && <Head title={t(title)} />}

            <SiteHeader />
            {auth?.user?.can_write === false && <ReadonlyBanner />}

            <div className="mx-auto flex w-full max-w-7xl flex-1 gap-6 px-4 py-6 pb-24 lg:pb-8">
                {sidebar.length > 0 && (
                    <aside className="hidden w-72 shrink-0 lg:block">
                        <SidebarWidgets widgets={sidebar} />
                    </aside>
                )}

                <main className="min-w-0 flex-1">
                    {children}

                    {sidebar.length > 0 && (
                        <Collapsible className="mt-6 lg:hidden">
                            <CollapsibleTrigger asChild>
                                <Button
                                    variant="outline"
                                    className="w-full justify-between"
                                >
                                    {t('core::public.sidebar.more')}
                                    <ChevronDown aria-hidden />
                                </Button>
                            </CollapsibleTrigger>
                            <CollapsibleContent className="mt-4">
                                <SidebarWidgets widgets={sidebar} />
                            </CollapsibleContent>
                        </Collapsible>
                    )}
                </main>
            </div>

            <SiteFooter />
            <MobileTabBar />
        </div>
    );
}
