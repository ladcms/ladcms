import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import type { ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@ladcms/core/components/ui/card';
import { useT } from '@ladcms/core/lib/i18n';
import { relativeTime } from '@ladcms/core/lib/relative-time';
import type { Profile } from '@ladcms/core/types/profile';

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid gap-0.5">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm break-words">{children}</dd>
        </div>
    );
}

function languageName(code: string, locale: string): string {
    try {
        return (
            new Intl.DisplayNames([locale], { type: 'language' }).of(code) ??
            code
        );
    } catch {
        return code;
    }
}

/** Карточка анкеты: регистрация, пол, дата рождения (если открыта), язык, последний визит, о себе, подпись. */
export function OverviewCard({ profile }: { profile: Profile }) {
    const t = useT();
    const { props } = usePage();
    const locale = typeof props.locale === 'string' ? props.locale : 'ru';
    const dateFormat = useMemo(
        () => new Intl.DateTimeFormat(locale, { dateStyle: 'long' }),
        [locale],
    );

    const lastSeen = profile.is_online
        ? t('core::public.profile.card.online')
        : profile.last_activity_at
          ? relativeTime(profile.last_activity_at, locale)
          : t('core::public.profile.card.never');

    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    <h2>{t('core::public.profile.card.title')}</h2>
                </CardTitle>
            </CardHeader>
            <CardContent>
                <dl className="grid gap-4">
                    {profile.created_at && (
                        <Row label={t('core::public.profile.card.joined')}>
                            {dateFormat.format(new Date(profile.created_at))}
                        </Row>
                    )}
                    {profile.sex && (
                        <Row label={t('core::public.profile.card.sex')}>
                            {t(`core::public.profile.card.${profile.sex}`)}
                        </Row>
                    )}
                    {profile.birth_date && (
                        <Row label={t('core::public.profile.card.birth_date')}>
                            {dateFormat.format(
                                new Date(`${profile.birth_date}T00:00:00`),
                            )}
                        </Row>
                    )}
                    <Row label={t('core::public.profile.card.language')}>
                        {languageName(profile.language, locale)}
                    </Row>
                    <Row label={t('core::public.profile.card.last_seen')}>
                        {lastSeen}
                    </Row>
                    {profile.email && (
                        <Row label={t('core::public.profile.card.email')}>
                            <a
                                href={`mailto:${profile.email}`}
                                className="underline-offset-4 hover:underline"
                            >
                                {profile.email}
                            </a>
                        </Row>
                    )}
                    {profile.about && (
                        <Row label={t('core::public.profile.card.about')}>
                            <span className="whitespace-pre-line">
                                {profile.about}
                            </span>
                        </Row>
                    )}
                    {profile.signature && (
                        <Row label={t('core::public.profile.card.signature')}>
                            <span className="whitespace-pre-line text-muted-foreground">
                                {profile.signature}
                            </span>
                        </Row>
                    )}
                </dl>
            </CardContent>
        </Card>
    );
}
