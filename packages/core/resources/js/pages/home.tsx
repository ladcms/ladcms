import { usePage } from '@inertiajs/react';
import { WidgetList } from '@ladcms/core/components/public/widget-list';
import { Seo } from '@ladcms/core/components/public/seo';
import { useT } from '@ladcms/core/lib/i18n';
import type { LayoutShared } from '@ladcms/core/types/layout';

/** Главная: виджеты слота `main` берутся из shared-пропа `layout.widgets.main`. */
export default function Home() {
    const t = useT();
    const { layout } = usePage<{ layout: LayoutShared }>().props;
    const { site, widgets } = layout;
    // Виджет Welcome сам показывает описание сайта — второй раз его не выводим.
    const hasWelcome = widgets.main.some(
        (widget) => widget.component === 'core::Welcome',
    );

    return (
        <>
            <Seo title={site.name} description={site.description} />
            <div className="flex flex-col gap-4">
                <header>
                    <h1 className="text-2xl font-semibold">
                        {t('core::public.home.heading', { name: site.name })}
                    </h1>
                    {site.description && !hasWelcome && (
                        <p className="mt-1 text-muted-foreground">
                            {site.description}
                        </p>
                    )}
                </header>
                {widgets.main.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-8 text-center">
                        <p className="font-medium">
                            {t('core::public.home.empty')}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('core::public.home.empty_text')}
                        </p>
                    </div>
                ) : (
                    <WidgetList widgets={widgets.main} />
                )}
            </div>
        </>
    );
}
