import { Link, router } from '@inertiajs/react';
import { Seo } from '@ladcms/core/components/public/seo';
import { useT } from '@ladcms/core/lib/i18n';
import { logout } from '@/routes';

type Props = {
    reason_code: string;
    comment: string | null;
    expires_at: string | null;
};

export default function Banned({ reason_code, comment, expires_at }: Props) {
    const t = useT();

    return (
        <>
            <Seo title={t('core::public.banned.title')} />
            <div className="flex min-h-[50vh] items-center justify-center">
                <div className="max-w-md space-y-3 text-center">
                    <h1 className="text-3xl font-bold">
                        {t('core::public.banned.title')}
                    </h1>
                    <p>
                        {t('core::public.banned.reason', {
                            code: reason_code,
                        })}
                    </p>
                    {comment && (
                        <p className="text-muted-foreground">{comment}</p>
                    )}
                    <p>
                        {expires_at
                            ? t('core::public.banned.until', {
                                  date: new Date(expires_at).toLocaleString(),
                              })
                            : t('core::public.banned.forever')}
                    </p>
                    <Link
                        href={logout()}
                        as="button"
                        onClick={() => router.flushAll()}
                        className="underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        {t('core::public.banned.logout')}
                    </Link>
                </div>
            </div>
        </>
    );
}
