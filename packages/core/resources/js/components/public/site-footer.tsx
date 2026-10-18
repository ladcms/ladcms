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

export function SiteFooter() {
    const t = useT();
    const { layout, locale, locales } = usePage<{
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
                <div className="flex items-center gap-4">
                    <Link href="/rules" className="hover:text-foreground">
                        {t('core::public.footer.rules')}
                    </Link>
                    {available.length > 1 && (
                        <Select
                            value={locale}
                            onValueChange={(value) =>
                                // Маршрут `/locale` появляется в Task 6.
                                router.post(
                                    '/locale',
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
                            <SelectContent>
                                {available.map(([code, label]) => (
                                    <SelectItem key={code} value={code}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </div>
            </div>
        </footer>
    );
}
