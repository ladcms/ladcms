import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageHeader } from '@ladcms/core/components/page-header';
import { useT } from '@ladcms/core/lib/i18n';
import { cn } from '@ladcms/core/lib/utils';
import { appearance, notifications, privacy, profile } from '@/routes/settings';
import { edit as security } from '@/routes/security';

/** Каркас страниц настроек: заголовок + вкладки (вертикально с `md`, горизонтальный скролл на телефоне); вложен в `PublicLayout`. */
export default function SettingsLayout({ children }: { children: ReactNode }) {
    const t = useT();
    const { url } = usePage();
    const path = url.split('?')[0];

    const items = [
        { key: 'profile', href: profile().url },
        { key: 'privacy', href: privacy().url },
        { key: 'notifications', href: notifications().url },
        { key: 'security', href: security().url },
        { key: 'appearance', href: appearance().url },
    ];

    return (
        <div className="mx-auto w-full max-w-4xl space-y-6">
            <PageHeader
                title={t('core::public.settings.title')}
                description={t('core::public.settings.description')}
            />
            <div className="flex flex-col gap-6 md:flex-row">
                <nav
                    aria-label={t('core::public.settings.nav_label')}
                    className="flex [scrollbar-width:none] gap-1 overflow-x-auto overflow-y-hidden md:w-48 md:shrink-0 md:flex-col md:overflow-visible [&::-webkit-scrollbar]:hidden"
                >
                    {items.map((item) => {
                        const active =
                            path === item.href ||
                            path.startsWith(`${item.href}/`);

                        return (
                            <Link
                                key={item.key}
                                href={item.href}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'rounded-md px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors',
                                    active
                                        ? 'bg-muted text-foreground'
                                        : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                                )}
                            >
                                {t(`core::public.settings.tabs.${item.key}`)}
                            </Link>
                        );
                    })}
                </nav>
                <div className="min-w-0 flex-1 space-y-6">{children}</div>
            </div>
        </div>
    );
}
