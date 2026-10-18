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
import { useT } from '@ladcms/core/lib/i18n';

type ComplaintStatus = 'pending' | 'reviewed' | 'resolved' | 'rejected';
type StatusFilter = ComplaintStatus | 'all';

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
    const t = useT();
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
            label: t('core::admin.moderation.complaints.columns.author'),
            render: (row) => <UserLink user={row.author} />,
        },
        {
            key: 'target',
            label: t('core::admin.moderation.complaints.columns.target'),
            render: (row) => <UserLink user={row.target_user} />,
        },
        {
            key: 'object',
            label: t('core::admin.moderation.complaints.columns.object'),
            render: (row) =>
                row.reportable === null ? (
                    <span className="text-muted-foreground">—</span>
                ) : (
                    `${row.reportable.type} #${row.reportable.id ?? ''}`
                ),
        },
        {
            key: 'reason_code',
            label: t('core::admin.moderation.complaints.columns.reason'),
        },
        {
            key: 'comment',
            label: t('core::admin.moderation.complaints.columns.comment'),
            className: 'max-w-xs whitespace-normal break-words',
        },
        {
            key: 'status',
            label: t('core::admin.moderation.complaints.columns.status'),
            sortable: true,
            render: (row) => (
                <Badge
                    variant={row.status === 'pending' ? 'default' : 'secondary'}
                >
                    {t(
                        `core::admin.moderation.complaints.statuses.${row.status}`,
                    )}
                </Badge>
            ),
        },
        {
            key: 'created_at',
            label: t('core::admin.moderation.complaints.columns.created_at'),
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
            label: t('core::admin.moderation.complaints.columns.actions'),
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
                                {t(
                                    `core::admin.moderation.complaints.review_actions.${status}`,
                                )}
                            </Button>
                        ))}
                    </div>
                ) : null,
        });
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title={t('core::admin.moderation.complaints.title')}
                description={`${t('core::admin.moderation.complaints.total')}: ${complaints.meta.total}`}
            />

            <Select
                value={filters.status}
                onValueChange={(status) => visit(status as StatusFilter)}
            >
                <SelectTrigger
                    aria-label={t('core::admin.moderation.complaints.status')}
                    className="sm:w-48"
                >
                    <SelectValue
                        placeholder={t(
                            'core::admin.moderation.complaints.status',
                        )}
                    />
                </SelectTrigger>
                <SelectContent>
                    {statuses.map((status) => (
                        <SelectItem key={status} value={status}>
                            {t(
                                `core::admin.moderation.complaints.statuses.${status}`,
                            )}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <DataTable
                columns={columns}
                rows={complaints.data}
                pagination={complaints.meta}
                sort={{ column: filters.sort, direction: filters.direction }}
                emptyText={t('core::admin.moderation.complaints.empty')}
                rowKey={(row) => row.id}
            />
        </div>
    );
}

ComplaintsIndex.layout = {
    title: 'core::admin.moderation.complaints.title',
    breadcrumbs: [{ title: 'core::admin.moderation.complaints.title' }],
};
