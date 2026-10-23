import { Link } from '@inertiajs/react';
import { Seo } from '@ladcms/core/components/public/seo';
import { Button } from '@ladcms/core/components/ui/button';
import { useT } from '@ladcms/core/lib/i18n';
import { home } from '@/routes';

type Props = {
    status: number;
    title: string;
    message: string;
};

/**
 * Страница ошибки 403/404/405/500/503 (`ErrorPageRenderer`). Может отрисоваться без shared-пропов `layout`/`auth`
 * (ошибка случилась до middleware), поэтому ничего из них не требует.
 */
export default function ErrorPage({ status, title, message }: Props) {
    const t = useT();

    return (
        <>
            <Seo title={`${status} — ${title}`} description={message} />
            <div className="flex min-h-[50vh] flex-col items-center justify-center gap-3 text-center">
                <p
                    aria-hidden
                    className="text-6xl font-bold tracking-tight text-muted-foreground tabular-nums"
                >
                    {status}
                </p>
                <h1 className="text-2xl font-semibold">{title}</h1>
                <p className="max-w-md text-muted-foreground">{message}</p>
                <Button asChild className="mt-2">
                    <Link href={home()}>
                        {t('core::public.errors.back_home')}
                    </Link>
                </Button>
            </div>
        </>
    );
}
