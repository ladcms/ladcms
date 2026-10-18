import { Link, router, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import UserBanController from '@/actions/Ladcms/Core/Http/Controllers/Admin/UserBanController';
import UserController from '@/actions/Ladcms/Core/Http/Controllers/Admin/UserController';
import { ConfirmDialog } from '@ladcms/core/components/confirm-dialog';
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

type BanStatus = 'active' | 'expired' | 'all';

const STATUSES: BanStatus[] = ['active', 'expired', 'all'];

type UserRef = { id: number; login: string };

/** `BanResource`. */
type BanRow = {
    id: number;
    user: UserRef | null;
    banned_by: UserRef | null;
    type: string;
    reason_code: string;
    comment: string | null;
    starts_at: string;
    expires_at: string | null;
    is_active: boolean;
};

type Filters = {
    status: BanStatus;
    sort: string;
    direction: SortDirection;
};

type Props = {
    bans: { data: BanRow[]; meta: Paginator };
    filters: Filters;
    can: { lift: boolean };
};

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

export default function BansIndex({ bans, filters, can }: Props) {
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
    const formatDate = (value: string) => dateFormat.format(new Date(value));

    const visit = (status: BanStatus) => {
        router.get(
            path,
            { status, sort: filters.sort, direction: filters.direction },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const columns: Column<BanRow>[] = [
        {
            key: 'user',
            label: t('core::admin.moderation.bans.columns.user'),
            render: (ban) => <UserLink user={ban.user} />,
        },
        {
            key: 'banned_by',
            label: t('core::admin.moderation.bans.columns.banned_by'),
            render: (ban) => <UserLink user={ban.banned_by} />,
        },
        {
            key: 'type',
            label: t('core::admin.moderation.bans.columns.type'),
            render: (ban) => <Badge variant="outline">{ban.type}</Badge>,
        },
        {
            key: 'reason_code',
            label: t('core::admin.moderation.bans.columns.reason'),
        },
        {
            key: 'starts_at',
            label: t('core::admin.moderation.bans.columns.starts_at'),
            sortable: true,
            className: 'whitespace-nowrap',
            render: (ban) => formatDate(ban.starts_at),
        },
        {
            key: 'expires_at',
            label: t('core::admin.moderation.bans.columns.expires_at'),
            sortable: true,
            className: 'whitespace-nowrap',
            render: (ban) =>
                ban.expires_at === null
                    ? t('core::admin.moderation.bans.permanent')
                    : formatDate(ban.expires_at),
        },
        {
            key: 'comment',
            label: t('core::admin.moderation.bans.columns.comment'),
            className: 'max-w-xs whitespace-normal break-words',
            render: (ban) => ban.comment ?? '—',
        },
    ];

    if (can.lift) {
        columns.push({
            key: 'actions',
            label: t('core::admin.moderation.bans.columns.actions'),
            render: (ban) =>
                ban.is_active && ban.user !== null ? (
                    <ConfirmDialog
                        trigger={
                            <Button size="sm" variant="outline">
                                {t('core::admin.moderation.bans.lift')}
                            </Button>
                        }
                        title={t('core::admin.moderation.bans.lift_title')}
                        description={t(
                            'core::admin.moderation.bans.lift_description',
                        )}
                        confirmLabel={t('core::admin.moderation.bans.lift')}
                        destructive
                        onConfirm={() =>
                            router.delete(
                                UserBanController.destroy.url({
                                    user: ban.user!.id,
                                    ban: ban.id,
                                }),
                                { preserveScroll: true },
                            )
                        }
                    />
                ) : null,
        });
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title={t('core::admin.moderation.bans.title')}
                description={`${t('core::admin.moderation.bans.total')}: ${bans.meta.total}`}
            />

            <Select
                value={filters.status}
                onValueChange={(status) => visit(status as BanStatus)}
            >
                <SelectTrigger
                    aria-label={t('core::admin.moderation.bans.status')}
                    className="sm:w-48"
                >
                    <SelectValue
                        placeholder={t('core::admin.moderation.bans.status')}
                    />
                </SelectTrigger>
                <SelectContent>
                    {STATUSES.map((status) => (
                        <SelectItem key={status} value={status}>
                            {t(
                                `core::admin.moderation.bans.statuses.${status}`,
                            )}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <DataTable
                columns={columns}
                rows={bans.data}
                pagination={bans.meta}
                sort={{ column: filters.sort, direction: filters.direction }}
                emptyText={t('core::admin.moderation.bans.empty')}
                rowKey={(ban) => ban.id}
            />
        </div>
    );
}

BansIndex.layout = {
    title: 'core::admin.moderation.bans.title',
    breadcrumbs: [{ title: 'core::admin.moderation.bans.title' }],
};
