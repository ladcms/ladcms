import { Link, router, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import ComplaintsController from '@/actions/Ladcms/Core/Http/Controllers/Admin/ComplaintsController';
import UserController from '@/actions/Ladcms/Core/Http/Controllers/Admin/UserController';
import { DataTable } from '@ladcms/core/components/data-table';
import { PageHeader } from '@ladcms/core/components/page-header';
import { Badge } from '@ladcms/core/components/ui/badge';
import { Button } from '@ladcms/core/components/ui/button';
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

type ComplaintStatus = 'pending' | 'reviewed' | 'resolved' | 'rejected';
type StatusFilter = ComplaintStatus | 'all';

const t = {
    title: 'Жалобы',
    total: 'Всего',
    status: 'Статус',
    statuses: {
        pending: 'Ожидает',
        reviewed: 'Рассмотрена',
        resolved: 'Решена',
        rejected: 'Отклонена',
        all: 'Все',
    } satisfies Record<StatusFilter, string>,
    columns: {
        author: 'Автор',
        target: 'Цель',
        object: 'Объект',
        reason: 'Код',
        comment: 'Комментарий',
        status: 'Статус',
        created_at: 'Создана',
        actions: 'Действия',
    },
    reviewActions: {
        reviewed: 'Рассмотрена',
        resolved: 'Решена',
        rejected: 'Отклонить',
    } satisfies Record<Exclude<ComplaintStatus, 'pending'>, string>,
    empty: 'Жалоб нет',
};

type UserRef = { id: number; login: string };

/** `ComplaintResource`. */
type ComplaintRow = {
    id: number;
    author: UserRef | null;
    target_user: UserRef | null;
    reportable: { type: string; id: number | null } | null;
    reason_code: string;
    comment: string;
    status: ComplaintStatus;
    reviewer: UserRef | null;
    reviewed_at: string | null;
    created_at: string | null;
};

type Filters = {
    status: StatusFilter;
    sort: string;
    direction: SortDirection;
};

type Props = {
    complaints: { data: ComplaintRow[]; meta: Paginator };
    filters: Filters;
    statuses: StatusFilter[];
    can: { review: boolean };
};

const REVIEW_STATUSES = ['reviewed', 'resolved', 'rejected'] as const;

function UserLink({ user }: { user: UserRef | null }) {
    if (user === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <Link
            href={UserController.show.url(user.id)}
            className="font-medium hover:underline"
        >
            {user.login}
        </Link>
    );
}

export default function ComplaintsIndex({
    complaints,
    filters,
    statuses,
    can,
}: Props) {
    const { url, props } = usePage();
    const locale = typeof props.locale === 'string' ? props.locale : 'ru';
    const path = url.split('?')[0] ?? url;

    const dateFormat = useMemo(
        () =>
            new Intl.DateTimeFormat(locale, {
                dateStyle: 'medium',
                timeStyle: 'short',
            }),
        [locale],
    );

    const visit = (status: StatusFilter) => {
        router.get(
            path,
            { status, sort: filters.sort, direction: filters.direction },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const review = (id: number, status: ComplaintStatus) => {
        router.patch(
            ComplaintsController.review.url(id),
            { status },
            { preserveScroll: true },
        );
    };

    const columns: Column<ComplaintRow>[] = [
        {
            key: 'author',
            label: t.columns.author,
            render: (row) => <UserLink user={row.author} />,
        },
        {
            key: 'target',
            label: t.columns.target,
            render: (row) => <UserLink user={row.target_user} />,
        },
        {
            key: 'object',
            label: t.columns.object,
            render: (row) =>
                row.reportable === null ? (
                    <span className="text-muted-foreground">—</span>
                ) : (
                    `${row.reportable.type} #${row.reportable.id ?? ''}`
                ),
        },
        { key: 'reason_code', label: t.columns.reason },
        {
            key: 'comment',
            label: t.columns.comment,
            className: 'max-w-xs whitespace-normal break-words',
        },
        {
            key: 'status',
            label: t.columns.status,
            sortable: true,
            render: (row) => (
                <Badge
                    variant={row.status === 'pending' ? 'default' : 'secondary'}
                >
                    {t.statuses[row.status]}
                </Badge>
            ),
        },
        {
            key: 'created_at',
            label: t.columns.created_at,
            sortable: true,
            className: 'whitespace-nowrap',
            render: (row) =>
                row.created_at === null
                    ? '—'
                    : dateFormat.format(new Date(row.created_at)),
        },
    ];

    if (can.review) {
        columns.push({
            key: 'actions',
            label: t.columns.actions,
            render: (row) =>
                row.status === 'pending' ? (
                    <div className="flex flex-wrap gap-1.5">
                        {REVIEW_STATUSES.map((status) => (
                            <Button
                                key={status}
                                size="sm"
                                variant={
                                    status === 'rejected'
                                        ? 'outline'
                                        : 'secondary'
                                }
                                onClick={() => review(row.id, status)}
                            >
                                {t.reviewActions[status]}
                            </Button>
                        ))}
                    </div>
                ) : null,
        });
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title={t.title}
                description={`${t.total}: ${complaints.meta.total}`}
            />

            <Select
                value={filters.status}
                onValueChange={(status) => visit(status as StatusFilter)}
            >
                <SelectTrigger aria-label={t.status} className="sm:w-48">
                    <SelectValue placeholder={t.status} />
                </SelectTrigger>
                <SelectContent>
                    {statuses.map((status) => (
                        <SelectItem key={status} value={status}>
                            {t.statuses[status]}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <DataTable
                columns={columns}
                rows={complaints.data}
                pagination={complaints.meta}
                sort={{ column: filters.sort, direction: filters.direction }}
                emptyText={t.empty}
                rowKey={(row) => row.id}
            />
        </div>
    );
}

ComplaintsIndex.layout = {
    title: t.title,
    breadcrumbs: [{ title: t.title }],
};
