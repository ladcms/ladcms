import { router, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { Appearance } from '@ladcms/core/hooks/use-appearance';
import { useAppearance } from '@ladcms/core/hooks/use-appearance';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@ladcms/core/components/ui/card';
import { Label } from '@ladcms/core/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@ladcms/core/components/ui/select';
import { Seo } from '@ladcms/core/components/public/seo';
import { useT } from '@ladcms/core/lib/i18n';
import { cn } from '@ladcms/core/lib/utils';
import { update as updateLocale } from '@/routes/locale';

const THEMES: { value: Appearance; icon: LucideIcon }[] = [
    { value: 'light', icon: Sun },
    { value: 'dark', icon: Moon },
    { value: 'system', icon: Monitor },
];

/** Тема — только на клиенте (localStorage + cookie); язык — `POST /locale` (users.language / cookie). */
export default function AppearanceSettings() {
    const t = useT();
    const { appearance, updateAppearance } = useAppearance();
    const { locales, locale } = usePage<{
        locales: Record<string, string>;
        locale: string;
    }>().props;

    return (
        <>
            <Seo title={t('core::public.settings.appearance.title')} />

            <Card>
                <CardHeader>
                    <CardTitle>
                        {t('core::public.settings.appearance.title')}
                    </CardTitle>
                    <CardDescription>
                        {t('core::public.settings.appearance.description')}
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="grid gap-2">
                        <span
                            id="appearance-theme"
                            className="text-sm leading-none font-medium"
                        >
                            {t('core::public.settings.appearance.theme')}
                        </span>
                        <div
                            role="radiogroup"
                            aria-labelledby="appearance-theme"
                            className="inline-flex w-fit gap-1 rounded-lg bg-muted p-1"
                        >
                            {THEMES.map(({ value, icon: Icon }) => (
                                <button
                                    key={value}
                                    type="button"
                                    role="radio"
                                    aria-checked={appearance === value}
                                    onClick={() => updateAppearance(value)}
                                    className={cn(
                                        'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm transition-colors',
                                        appearance === value
                                            ? 'bg-background shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    <Icon className="size-4" aria-hidden />
                                    {t(
                                        `core::public.settings.appearance.${value}`,
                                    )}
                                </button>
                            ))}
                        </div>
                    </div>

                    {locales && (
                        <div className="grid gap-2">
                            <Label htmlFor="appearance-language">
                                {t('core::public.settings.appearance.language')}
                            </Label>
                            <Select
                                value={locale}
                                onValueChange={(value) =>
                                    router.post(
                                        updateLocale().url,
                                        { locale: value },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <SelectTrigger
                                    id="appearance-language"
                                    className="w-full sm:w-64"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(locales).map(
                                        ([code, label]) => (
                                            <SelectItem key={code} value={code}>
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                </CardContent>
            </Card>
        </>
    );
}
