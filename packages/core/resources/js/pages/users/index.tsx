import { Link, router, usePage } from '@inertiajs/react';
import { Search, Users } from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { DataTablePagination } from '@ladcms/core/components/data-table';
import { EmptyState } from '@ladcms/core/components/empty-state';
import { PageHeader } from '@ladcms/core/components/page-header';
import { RoleBadge } from '@ladcms/core/components/public/role-badge';
import {
    Avatar,
    AvatarFallback,
    AvatarImage,
} from '@ladcms/core/components/ui/avatar';
import { Input } from '@ladcms/core/components/ui/input';
import { Label } from '@ladcms/core/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@ladcms/core/components/ui/select';
import { Switch } from '@ladcms/core/components/ui/switch';
import { Seo } from '@ladcms/core/components/public/seo';
import { useT } from '@ladcms/core/lib/i18n';
import { initials } from '@ladcms/core/lib/initials';
import type { Paginator, SortDirection } from '@ladcms/core/types/table';
import { show as userProfile } from '@/routes/users';

const SEARCH_DEBOUNCE_MS = 300;

/** `PublicUserResource` (email / birth_date приходят только по правилам приватности и здесь не используются). */
type PublicUser = {
    id: number;
    login: string;
    name: string | null;
    display_name: string;
    avatar_url: string | null;
    roles: { name: string; color: string | null }[];
    is_online: boolean;
    created_at: string | null;
};

type Filters = {
    q: string;
    sort: string;
    direction: SortDirection;
    online: boolean;
};

type Props = {
    /** Пагинированная `ResourceCollection`: `{ data, links, meta }`. */
    users: { data: PublicUser[]; meta: Paginator };
    filters: Filters;
};

const SORT_OPTIONS = [
    'created_at:desc',
    'created_at:asc',
    'login:asc',
    'login:desc',
    'last_activity_at:desc',
    'last_activity_at:asc',
] as const;

/** Как на сервере: обрезка пробелов и 100 символов. */
function normalizeQuery(value: string): string {
    return value.trim().slice(0, 100);
}

/** Параметры запроса без значений по умолчанию (чистый URL). */
function queryFrom(filters: Filters): Record<string, string> {
    const query: Record<string, string> = {};

    if (filters.q !== '') {
        query.q = filters.q;
    }

    if (filters.sort !== 'created_at' || filters.direction !== 'desc') {
        query.sort = filters.sort;
        query.direction = filters.direction;
    }

    if (filters.online) {
        query.online = '1';
    }

    return query;
}

export default function UsersIndex({ users, filters }: Props) {
    const t = useT();
    const { url, props } = usePage();
    const locale = typeof props.locale === 'string' ? props.locale : 'ru';
    const path = url.split('?')[0] ?? url;
    const onlineId = useId();
    const [search, setSearch] = useState(filters.q);
    const filtersRef = useRef(filters);
    filtersRef.current = filters;
    // Значение, которое мы сами отправили на сервер: отличает свой ответ от back/forward.
    const sentQuery = useRef(filters.q);

    const visit = (changes: Partial<Filters>) => {
        router.get(path, queryFrom({ ...filtersRef.current, ...changes }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    useEffect(() => {
        if (normalizeQuery(search) === filtersRef.current.q) {
            return;
        }

        const timer = window.setTimeout(() => {
            sentQuery.current = normalizeQuery(search);
            visit({ q: sentQuery.current });
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
        // `visit` читает актуальные фильтры через ref.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    // Назад/вперёд меняют `filters.q` без участия поля ввода — синхронизируем его.
    useEffect(() => {
        if (filters.q !== sentQuery.current) {
            sentQuery.current = filters.q;
            setSearch(filters.q);
        }
    }, [filters.q]);

    const dateFormat = useMemo(
        () => new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }),
        [locale],
    );

    const sortValue = `${filters.sort}:${filters.direction}`;
    const title = t('core::public.users.title');

    return (
        <>
            <Seo title={title} />
            <div className="space-y-6">
                <PageHeader
                    title={title}
                    description={`${t('core::public.users.total')}: ${users.meta.total}`}
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
                            placeholder={t('core::public.users.search')}
                            aria-label={t('core::public.users.search')}
                            className="pl-8"
                        />
                    </div>
                    <Select
                        value={sortValue}
                        onValueChange={(value) => {
                            const [sort, direction] = value.split(':');
                            visit({
                                sort: sort ?? 'created_at',
                                direction: direction as SortDirection,
                            });
                        }}
                    >
                        <SelectTrigger
                            aria-label={t('core::public.users.sort')}
                            className="sm:w-56"
                        >
                            <SelectValue
                                placeholder={t('core::public.users.sort')}
                            />
                        </SelectTrigger>
                        <SelectContent>
                            {SORT_OPTIONS.map((option) => (
                                <SelectItem key={option} value={option}>
                                    {t(
                                        `core::public.users.sort_options.${option.replace(':', '_')}`,
                                    )}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <div className="flex items-center gap-2">
                        <Switch
                            id={onlineId}
                            checked={filters.online}
                            onCheckedChange={(online) => visit({ online })}
                        />
                        <Label htmlFor={onlineId}>
                            {t('core::public.users.online_only')}
                        </Label>
                    </div>
                </div>

                {users.data.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title={t('core::public.users.empty')}
                        description={t('core::public.users.empty_text')}
                    />
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {users.data.map((user) => {
                            return (
                                <li
                                    key={user.id}
                                    className="flex items-center gap-3 rounded-xl border bg-card p-4"
                                >
                                    <div className="relative shrink-0">
                                        <Avatar className="size-12">
                                            {user.avatar_url && (
                                                <AvatarImage
                                                    src={user.avatar_url}
                                                    alt=""
                                                />
                                            )}
                                            <AvatarFallback>
                                                {initials(user.display_name)}
                                            </AvatarFallback>
                                        </Avatar>
                                        {user.is_online && (
                                            <span
                                                role="img"
                                                aria-label={t(
                                                    'core::public.users.online',
                                                )}
                                                title={t(
                                                    'core::public.users.online',
                                                )}
                                                className="absolute right-0 bottom-0 size-3 rounded-full border-2 border-card bg-emerald-500"
                                            />
                                        )}
                                    </div>
                                    <div className="grid min-w-0 gap-0.5">
                                        <Link
                                            href={userProfile(user.login)}
                                            className="truncate font-medium hover:underline"
                                        >
                                            {user.display_name}
                                        </Link>
                                        <RoleBadge roles={user.roles} />
                                        {user.created_at && (
                                            <span className="truncate text-xs text-muted-foreground">
                                                {t(
                                                    'core::public.users.joined',
                                                    {
                                                        date: dateFormat.format(
                                                            new Date(
                                                                user.created_at,
                                                            ),
                                                        ),
                                                    },
                                                )}
                                            </span>
                                        )}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}

                <DataTablePagination pagination={users.meta} currentUrl={url} />
            </div>
        </>
    );
}
