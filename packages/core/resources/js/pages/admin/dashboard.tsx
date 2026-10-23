import { Link } from '@inertiajs/react';
import UserController from '@/actions/Ladcms/Core/Http/Controllers/Admin/UserController';
import { PageHeader } from '@ladcms/core/components/page-header';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@ladcms/core/components/ui/card';
import { useT } from '@ladcms/core/lib/i18n';

type Stats = {
    users_total: number;
    users_online: number;
    users_today: number;
    pending_complaints: number;
    active_bans: number;
    modules: { installed: number; failed: number; disabled: number };
};

type ActivityRow = {
    id: number;
    event: string | null;
    causer: { id: number; login: string } | null;
    subject: { type?: string; id?: number; login?: string } | null;
    created_at: string | null;
};

type RecentUser = { id: number; login: string; created_at: string | null };

type Props = {
    stats: Stats;
    recent_activity: ActivityRow[] | null;
    recent_users: RecentUser[] | null;
};

function formatDate(value: string | null): string {
    return value === null ? '—' : new Date(value).toLocaleString();
}

export default function Dashboard({
    stats,
    recent_activity,
    recent_users,
}: Props) {
    const t = useT();
    const cards: { key: keyof Stats; value: number }[] = [
        { key: 'users_total', value: stats.users_total },
        { key: 'users_online', value: stats.users_online },
        { key: 'users_today', value: stats.users_today },
        { key: 'pending_complaints', value: stats.pending_complaints },
        { key: 'active_bans', value: stats.active_bans },
        { key: 'modules', value: stats.modules.installed },
    ];

    return (
        <div className="space-y-6">
            <PageHeader title={t('core::admin.dashboard.title')} />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {cards.map((card) => (
                    <Card key={card.key}>
                        <CardHeader>
                            <CardDescription>
                                {t(`core::admin.dashboard.cards.${card.key}`)}
                            </CardDescription>
                            <CardTitle
                                as="div"
                                className="text-3xl tabular-nums"
                            >
                                {card.value}
                            </CardTitle>
                            {card.key === 'modules' &&
                                stats.modules.failed > 0 && (
                                    <p className="text-sm text-destructive">
                                        {stats.modules.failed}{' '}
                                        {t('core::admin.dashboard.failed')}
                                    </p>
                                )}
                            {card.key === 'modules' &&
                                stats.modules.disabled > 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        {stats.modules.disabled}{' '}
                                        {t('core::admin.dashboard.disabled')}
                                    </p>
                                )}
                        </CardHeader>
                    </Card>
                ))}
            </div>

            {(recent_activity !== null || recent_users !== null) && (
                <div className="grid gap-4 lg:grid-cols-3">
                    {recent_activity !== null && (
                        <Card className="lg:col-span-2">
                            <CardHeader>
                                <CardTitle>
                                    {t('core::admin.dashboard.recent_activity')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {recent_activity.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('core::admin.dashboard.empty')}
                                    </p>
                                ) : (
                                    <ul className="divide-y text-sm">
                                        {recent_activity.map((item) => (
                                            <li
                                                key={item.id}
                                                className="flex flex-wrap items-baseline justify-between gap-2 py-2"
                                            >
                                                <span>
                                                    <span className="font-medium">
                                                        {item.causer?.login ??
                                                            t(
                                                                'core::admin.dashboard.system',
                                                            )}
                                                    </span>{' '}
                                                    <code className="text-muted-foreground">
                                                        {item.event}
                                                    </code>
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {formatDate(
                                                        item.created_at,
                                                    )}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {recent_users !== null && (
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {t('core::admin.dashboard.recent_users')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {recent_users.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('core::admin.dashboard.empty')}
                                    </p>
                                ) : (
                                    <ul className="divide-y text-sm">
                                        {recent_users.map((user) => (
                                            <li
                                                key={user.id}
                                                className="flex items-baseline justify-between gap-2 py-2"
                                            >
                                                <Link
                                                    href={UserController.show.url(
                                                        user.id,
                                                    )}
                                                    className="font-medium hover:underline"
                                                >
                                                    {user.login}
                                                </Link>
                                                <span className="text-muted-foreground">
                                                    {formatDate(
                                                        user.created_at,
                                                    )}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    )}
                </div>
            )}
        </div>
    );
}

Dashboard.layout = {
    title: 'core::admin.dashboard.title',
};
