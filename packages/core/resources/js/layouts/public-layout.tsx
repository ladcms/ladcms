import { usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { MobileTabBar } from '@ladcms/core/components/public/mobile-tab-bar';
import { ReadonlyBanner } from '@ladcms/core/components/public/readonly-banner';
import { WidgetList } from '@ladcms/core/components/public/widget-list';
import { SiteFooter } from '@ladcms/core/components/public/site-footer';
import { SiteHeader } from '@ladcms/core/components/public/site-header';
import { Button } from '@ladcms/core/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@ladcms/core/components/ui/collapsible';
import { useT } from '@ladcms/core/lib/i18n';
import { useMediaQuery } from '@ladcms/core/lib/use-media-query';
import type { CurrentUser } from '@ladcms/core/types/admin';
import type { LayoutShared } from '@ladcms/core/types/layout';

type PublicLayoutProps = {
    children: ReactNode;
};

export default function PublicLayout({ children }: PublicLayoutProps) {
    const t = useT();
    const { auth, layout } = usePage<{
        auth: { user: CurrentUser | null };
        layout?: LayoutShared | null;
    }>().props;
    const sidebar = layout?.widgets.sidebar ?? [];
    const isDesktop = useMediaQuery('(min-width: 1024px)');

    return (
        <div className="flex min-h-svh flex-col bg-background pb-[calc(3rem+1px+env(safe-area-inset-bottom))] text-foreground lg:pb-0">
            <a
                href="#main"
                className="sr-only focus-visible:not-sr-only focus-visible:fixed focus-visible:top-2 focus-visible:left-2 focus-visible:z-50 focus-visible:rounded-md focus-visible:bg-background focus-visible:px-4 focus-visible:py-2 focus-visible:text-sm focus-visible:font-medium focus-visible:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                {t('core::public.skip_to_content')}
            </a>

            <SiteHeader />
            {auth?.user?.can_write === false && <ReadonlyBanner />}

            <div className="mx-auto flex w-full max-w-7xl flex-1 gap-6 px-4 py-6">
                {isDesktop && sidebar.length > 0 && (
                    <aside
                        aria-label={t('core::public.sidebar.label')}
                        className="hidden w-72 shrink-0 lg:block"
                    >
                        <WidgetList widgets={sidebar} />
                    </aside>
                )}

                <main
                    id="main"
                    tabIndex={-1}
                    className="min-w-0 flex-1 outline-none"
                >
                    {children}

                    {!isDesktop && sidebar.length > 0 && (
                        <Collapsible className="mt-6">
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
                                <WidgetList widgets={sidebar} />
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
