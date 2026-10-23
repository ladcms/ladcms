import { Link, router, usePage } from '@inertiajs/react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@ladcms/core/components/ui/select';
import { useT } from '@ladcms/core/lib/i18n';
import type { LayoutShared } from '@ladcms/core/types/layout';
import { rules } from '@/routes';
import { update } from '@/routes/locale';
import { page as notificationsPage } from '@/routes/notifications';

export function SiteFooter() {
    const t = useT();
    const { auth, layout, locale, locales } = usePage<{
        auth?: { user: unknown } | null;
        layout?: LayoutShared | null;
        locale?: string;
        locales?: Record<string, string>;
    }>().props;
    const available = Object.entries(locales ?? {});

    return (
        <footer className="border-t border-border bg-card">
            <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-4 text-sm text-muted-foreground">
                <span>
                    {t('core::public.footer.copyright', {
                        year: new Date().getFullYear(),
                        name: layout?.site.name ?? '',
                    })}
                </span>
                <nav
                    aria-label={t('core::public.footer.nav')}
                    className="flex items-center gap-4"
                >
                    {auth?.user && (
                        <Link
                            href={notificationsPage()}
                            className="rounded-sm hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            {t('core::public.footer.notifications')}
                        </Link>
                    )}
                    <Link
                        href={rules()}
                        className="rounded-sm hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        {t('core::public.footer.rules')}
                    </Link>
                    {available.length > 1 && (
                        <Select
                            value={locale}
                            onValueChange={(value) =>
                                router.post(
                                    update.url(),
                                    { locale: value },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <SelectTrigger
                                size="sm"
                                aria-label={t('core::public.footer.language')}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent side="top" align="end">
                                {available.map(([code, label]) => (
                                    <SelectItem key={code} value={code}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </nav>
            </div>
        </footer>
    );
}
