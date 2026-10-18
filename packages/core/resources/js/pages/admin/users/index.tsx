import { Link, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { DataTable } from '@ladcms/core/components/data-table';
import { PageHeader } from '@ladcms/core/components/page-header';
import {
    Avatar,
    AvatarFallback,
    AvatarImage,
} from '@ladcms/core/components/ui/avatar';
import { Badge } from '@ladcms/core/components/ui/badge';
import { Input } from '@ladcms/core/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@ladcms/core/components/ui/select';
import type {
    Column,
    Paginator,
    SortDirection,
} from '@ladcms/core/types/table';

const t = {
    title: 'Пользователи',
    total: 'Всего',
    search: 'Поиск по логину, имени или email',
    role: 'Роль',
    allRoles: 'Все роли',
    status: 'Статус',
    statuses: {
        all: 'Все',
        online: 'Онлайн',
        banned: 'Забанен',
        suspicious: 'Подозрительный',
    },
    columns: {
        login: 'Логин',
        name: 'Имя',
        email: 'Email',
        roles: 'Роли',
        status: 'Статус',
        created_at: 'Регистрация',
        last_activity_at: 'Активность',
    },
    empty: 'Пользователи не найдены',
};

const SEARCH_DEBOUNCE_MS = 300;

type UserStatus = 'all' | 'banned' | 'suspicious' | 'online';

/** `UserListResource`. */
type UserRow = {
    id: number;
    login: string;
    name: string | null;
    display_name: string;
    email: string;
    avatar_url: string | null;
    roles: { name: string; color: string | null; level: number | null }[];
    is_online: boolean;
    is_banned: boolean;
    is_suspicious: boolean;
    created_at: string | null;
    last_activity_at: string | null;
};

/** `RoleResource`. */
type RoleOption = {
    id: number;
    name: string;
    level: number | null;
    color: string | null;
};

type Filters = {
    q: string;
    role: string;
    status: UserStatus;
    sort: string;
    direction: SortDirection;
};

type Props = {
    /** Пагинированная `ResourceCollection`: `{ data, links, meta }`. */
    users: { data: UserRow[]; meta: Paginator };
    filters: Filters;
    roles: RoleOption[];
};

const STATUSES: UserStatus[] = ['all', 'online', 'banned', 'suspicious'];

function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    const first = parts[0]?.[0] ?? '';
    const last = parts.length > 1 ? (parts[parts.length - 1]?.[0] ?? '') : '';

    return (first + last).toUpperCase() || '?';
}

/** Параметры запроса без значений по умолчанию (чистый URL). */
function queryFrom(filters: Filters): Record<string, string> {
    const query: Record<string, string> = {};

    if (filters.q !== '') {
        query.q = filters.q;
    }

    if (filters.role !== 'all') {
        query.role = filters.role;
    }

    if (filters.status !== 'all') {
        query.status = filters.status;
    }

    query.sort = filters.sort;
    query.direction = filters.direction;

    return query;
}

function StatusBadges({ user }: { user: UserRow }) {
    if (!user.is_online && !user.is_banned && !user.is_suspicious) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <div className="flex flex-wrap items-center gap-1.5">
            {user.is_online && (
                <Badge variant="outline">
                    <span
                        aria-hidden
                        className="size-2 rounded-full bg-emerald-500"
                    />
                    {t.statuses.online}
                </Badge>
            )}
            {user.is_banned && (
                <Badge variant="destructive">{t.statuses.banned}</Badge>
            )}
            {user.is_suspicious && (
                <Badge
                    variant="outline"
                    className="border-amber-500/50 text-amber-600 dark:text-amber-400"
                >
                    {t.statuses.suspicious}
                </Badge>
            )}
        </div>
    );
}

export default function UsersIndex({ users, filters, roles }: Props) {
    const { url, props } = usePage();
    const locale = typeof props.locale === 'string' ? props.locale : 'ru';
    const path = url.split('?')[0] ?? url;
    const [search, setSearch] = useState(filters.q);
    const filtersRef = useRef(filters);
    filtersRef.current = filters;

    const visit = (changes: Partial<Filters>) => {
        router.get(path, queryFrom({ ...filtersRef.current, ...changes }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    useEffect(() => {
        if (search === filtersRef.current.q) {
            return;
        }

        const timer = window.setTimeout(
            () => visit({ q: search }),
            SEARCH_DEBOUNCE_MS,
        );

        return () => window.clearTimeout(timer);
        // `visit` читает актуальные фильтры через ref.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const dateFormat = useMemo(
        () =>
            new Intl.DateTimeFormat(locale, {
                dateStyle: 'medium',
                timeStyle: 'short',
            }),
        [locale],
    );
    const formatDate = (value: string | null) =>
        value === null ? '—' : dateFormat.format(new Date(value));

    const columns: Column<UserRow>[] = [
        {
            key: 'login',
            label: t.columns.login,
            sortable: true,
            render: (user) => (
                <Link
                    href={`/admin/users/${user.id}`}
                    className="flex items-center gap-3 font-medium hover:underline"
                >
                    <Avatar className="size-8">
                        {user.avatar_url && (
                            <AvatarImage
                                src={user.avatar_url}
                                alt={user.display_name}
                            />
                        )}
                        <AvatarFallback>
                            {initials(user.display_name)}
                        </AvatarFallback>
                    </Avatar>
                    {user.login}
                </Link>
            ),
        },
        { key: 'name', label: t.columns.name, sortable: true },
        { key: 'email', label: t.columns.email, sortable: true },
        {
            key: 'roles',
            label: t.columns.roles,
            render: (user) => (
                <div className="flex flex-wrap gap-1">
                    {user.roles.map((role) => (
                        <Badge
                            key={role.name}
                            variant={role.color ? 'default' : 'secondary'}
                            style={
                                role.color
                                    ? { backgroundColor: role.color }
                                    : undefined
                            }
                        >
                            {role.name}
                        </Badge>
                    ))}
                </div>
            ),
        },
        {
            key: 'status',
            label: t.columns.status,
            render: (user) => <StatusBadges user={user} />,
        },
        {
            key: 'created_at',
            label: t.columns.created_at,
            sortable: true,
            className: 'whitespace-nowrap',
            render: (user) => formatDate(user.created_at),
        },
        {
            key: 'last_activity_at',
            label: t.columns.last_activity_at,
            sortable: true,
            className: 'whitespace-nowrap',
            render: (user) => formatDate(user.last_activity_at),
        },
    ];

    return (
        <div className="space-y-6">
            <PageHeader
                title={t.title}
                description={`${t.total}: ${users.meta.total}`}
            />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div className="relative flex-1 sm:max-w-sm">
                    <Search
                        aria-hidden
                        className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t.search}
                        aria-label={t.search}
                        className="pl-8"
                    />
                </div>
                <Select
                    value={filters.role}
                    onValueChange={(role) => visit({ role })}
                >
                    <SelectTrigger aria-label={t.role} className="sm:w-48">
                        <SelectValue placeholder={t.role} />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">{t.allRoles}</SelectItem>
                        {roles.map((role) => (
                            <SelectItem key={role.id} value={role.name}>
                                {role.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Select
                    value={filters.status}
                    onValueChange={(status) =>
                        visit({ status: status as UserStatus })
                    }
                >
                    <SelectTrigger aria-label={t.status} className="sm:w-48">
                        <SelectValue placeholder={t.status} />
                    </SelectTrigger>
                    <SelectContent>
                        {STATUSES.map((status) => (
                            <SelectItem key={status} value={status}>
                                {t.statuses[status]}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <DataTable
                columns={columns}
                rows={users.data}
                pagination={users.meta}
                sort={{ column: filters.sort, direction: filters.direction }}
                emptyText={t.empty}
                rowKey={(user) => user.id}
            />
        </div>
    );
}

UsersIndex.layout = {
    title: t.title,
    breadcrumbs: [{ title: t.title }],
};
