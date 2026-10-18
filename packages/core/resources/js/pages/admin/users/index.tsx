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
import { roleName, useT } from '@ladcms/core/lib/i18n';

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
    const t = useT();
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
                    {t('core::admin.users.statuses.online')}
                </Badge>
            )}
            {user.is_banned && (
                <Badge variant="destructive">
                    {t('core::admin.users.statuses.banned')}
                </Badge>
            )}
            {user.is_suspicious && (
                <Badge
                    variant="outline"
                    className="border-amber-500/50 text-amber-600 dark:text-amber-400"
                >
                    {t('core::admin.users.statuses.suspicious')}
                </Badge>
            )}
        </div>
    );
}

export default function UsersIndex({ users, filters, roles }: Props) {
    const t = useT();
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
            label: t('core::admin.users.columns.login'),
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
        {
            key: 'name',
            label: t('core::admin.users.columns.name'),
            sortable: true,
        },
        {
            key: 'email',
            label: t('core::admin.users.columns.email'),
            sortable: true,
        },
        {
            key: 'roles',
            label: t('core::admin.users.columns.roles'),
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
                            {roleName(t, role.name)}
                        </Badge>
                    ))}
                </div>
            ),
        },
        {
            key: 'status',
            label: t('core::admin.users.columns.status'),
            render: (user) => <StatusBadges user={user} />,
        },
        {
            key: 'created_at',
            label: t('core::admin.users.columns.created_at'),
            sortable: true,
            className: 'whitespace-nowrap',
            render: (user) => formatDate(user.created_at),
        },
        {
            key: 'last_activity_at',
            label: t('core::admin.users.columns.last_activity_at'),
            sortable: true,
            className: 'whitespace-nowrap',
            render: (user) => formatDate(user.last_activity_at),
        },
    ];

    return (
        <div className="space-y-6">
            <PageHeader
                title={t('core::admin.users.title')}
                description={`${t('core::admin.users.total')}: ${users.meta.total}`}
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
                        placeholder={t('core::admin.users.search')}
                        aria-label={t('core::admin.users.search')}
                        className="pl-8"
                    />
                </div>
                <Select
                    value={filters.role}
                    onValueChange={(role) => visit({ role })}
                >
                    <SelectTrigger
                        aria-label={t('core::admin.users.role')}
                        className="sm:w-48"
                    >
                        <SelectValue
                            placeholder={t('core::admin.users.role')}
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">
                            {t('core::admin.users.all_roles')}
                        </SelectItem>
                        {roles.map((role) => (
                            <SelectItem key={role.id} value={role.name}>
                                {roleName(t, role.name)}
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
                    <SelectTrigger
                        aria-label={t('core::admin.users.status')}
                        className="sm:w-48"
                    >
                        <SelectValue
                            placeholder={t('core::admin.users.status')}
                        />
                    </SelectTrigger>
                    <SelectContent>
                        {STATUSES.map((status) => (
                            <SelectItem key={status} value={status}>
                                {t(`core::admin.users.statuses.${status}`)}
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
                emptyText={t('core::admin.users.empty')}
                rowKey={(user) => user.id}
            />
        </div>
    );
}

UsersIndex.layout = {
    title: 'core::admin.users.title',
    breadcrumbs: [{ title: 'core::admin.users.title' }],
};
