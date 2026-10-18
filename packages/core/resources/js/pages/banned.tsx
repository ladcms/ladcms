import { Head, Link } from '@inertiajs/react';

type Props = {
    reason_code: string;
    comment: string | null;
    expires_at: string | null;
};

export default function Banned({ reason_code, comment, expires_at }: Props) {
    return (
        <>
            <Head title="Доступ ограничен" />
            <main className="flex min-h-screen items-center justify-center p-4">
                <div className="max-w-md space-y-3 text-center">
                    <h1 className="text-3xl font-bold">Доступ ограничен</h1>
                    <p>
                        Причина: <code>{reason_code}</code>
                    </p>
                    {comment && (
                        <p className="text-muted-foreground">{comment}</p>
                    )}
                    <p>
                        {expires_at
                            ? `До: ${new Date(expires_at).toLocaleString()}`
                            : 'Бессрочно'}
                    </p>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="underline"
                    >
                        Выйти
                    </Link>
                </div>
            </main>
        </>
    );
}
