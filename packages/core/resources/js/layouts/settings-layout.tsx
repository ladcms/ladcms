import { Link, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import type { KeyboardEvent, ReactNode } from 'react';
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

    const nav = useRef<HTMLElement>(null);

    // Стрелки/Home/End переводят фокус между пунктами (это ссылки на отдельные страницы, не ARIA-табы).
    const onKeyDown = (event: KeyboardEvent<HTMLElement>) => {
        const links = Array.from(
            nav.current?.querySelectorAll<HTMLAnchorElement>('a') ?? [],
        );
        const index = links.indexOf(
            document.activeElement as HTMLAnchorElement,
        );
        if (index === -1) {
            return;
        }

        const last = links.length - 1;
        const target =
            event.key === 'ArrowRight' || event.key === 'ArrowDown'
                ? (index + 1) % links.length
                : event.key === 'ArrowLeft' || event.key === 'ArrowUp'
                  ? (index - 1 + links.length) % links.length
                  : event.key === 'Home'
                    ? 0
                    : event.key === 'End'
                      ? last
                      : -1;
        if (target === -1) {
            return;
        }

        event.preventDefault();
        links[target]?.focus();
    };

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
                    ref={nav}
                    onKeyDown={onKeyDown}
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
                                    'rounded-md px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
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
