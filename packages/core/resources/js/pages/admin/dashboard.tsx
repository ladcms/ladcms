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

const t = {
    title: 'Панель управления',
    cards: {
        users_total: 'Пользователей',
        users_online: 'Сейчас онлайн',
        users_today: 'Новых сегодня',
        pending_complaints: 'Жалоб на рассмотрении',
        active_bans: 'Активных банов',
        modules: 'Модулей установлено',
    },
    failed: 'с ошибками',
    recentActivity: 'Последние действия',
    recentUsers: 'Новые пользователи',
    empty: 'Пока ничего нет',
    system: 'система',
};

type Stats = {
    users_total: number;
    users_online: number;
    users_today: number;
    pending_complaints: number;
    active_bans: number;
    modules: { installed: number; failed: number };
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
    recent_activity: ActivityRow[];
    recent_users: RecentUser[];
};

function formatDate(value: string | null): string {
    return value === null ? '—' : new Date(value).toLocaleString();
}

export default function Dashboard({
    stats,
    recent_activity,
    recent_users,
}: Props) {
    const cards: { key: keyof typeof t.cards; value: number }[] = [
        { key: 'users_total', value: stats.users_total },
        { key: 'users_online', value: stats.users_online },
        { key: 'users_today', value: stats.users_today },
        { key: 'pending_complaints', value: stats.pending_complaints },
        { key: 'active_bans', value: stats.active_bans },
        { key: 'modules', value: stats.modules.installed },
    ];

    return (
        <div className="space-y-6">
            <PageHeader title={t.title} />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {cards.map((card) => (
                    <Card key={card.key}>
                        <CardHeader>
                            <CardDescription>{t.cards[card.key]}</CardDescription>
                            <CardTitle className="text-3xl tabular-nums">
                                {card.value}
                            </CardTitle>
                            {card.key === 'modules' &&
                                stats.modules.failed > 0 && (
                                    <p className="text-sm text-destructive">
                                        {stats.modules.failed} {t.failed}
                                    </p>
                                )}
                        </CardHeader>
                    </Card>
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>{t.recentActivity}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recent_activity.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t.empty}
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
                                                    t.system}
                                            </span>{' '}
                                            <code className="text-muted-foreground">
                                                {item.event}
                                            </code>
                                        </span>
                                        <span className="text-muted-foreground">
                                            {formatDate(item.created_at)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t.recentUsers}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recent_users.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t.empty}
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
                                            {formatDate(user.created_at)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

Dashboard.layout = {
    title: t.title,
};
