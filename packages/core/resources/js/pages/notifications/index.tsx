import { Link, router, useHttp, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { DataTablePagination } from '@ladcms/core/components/data-table';
import { EmptyState } from '@ladcms/core/components/empty-state';
import { PageHeader } from '@ladcms/core/components/page-header';
import { NOTIFICATIONS_CHANGED_EVENT } from '@ladcms/core/components/notification-bell';
import { Button } from '@ladcms/core/components/ui/button';
import { Seo } from '@ladcms/core/components/public/seo';
import { useT } from '@ladcms/core/lib/i18n';
import { relativeTime } from '@ladcms/core/lib/relative-time';
import { cn } from '@ladcms/core/lib/utils';
import type { Paginator } from '@ladcms/core/types/table';
import { readAll } from '@/routes/notifications';

type NotificationRow = {
    id: string;
    type: string;
    /** Ключ перевода (`<module>::public.…`). */
    title: string;
    params: Record<string, string | number>;
    url: string | null;
    read_at: string | null;
    created_at: string;
};

type Props = {
    notifications: { data: NotificationRow[]; meta: Paginator };
};

export default function NotificationsIndex({ notifications }: Props) {
    const t = useT();
    const { url, props } = usePage<{ locale?: string }>();
    const locale = props.locale ?? 'en';
    const http = useHttp<Record<string, never>, unknown>(
        'post',
        readAll().url,
        {},
    );

    const markAll = async () => {
        try {
            await http.post(readAll().url);
        } catch {
            // Список перезагрузится ниже и покажет фактическое состояние.
        }
        router.reload();
        // Колокольчик в шапке перечитывает счётчик сразу, а не на следующем опросе.
        window.dispatchEvent(new CustomEvent(NOTIFICATIONS_CHANGED_EVENT));
    };

    return (
        <>
            <Seo title={t('core::public.notifications.page_title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('core::public.notifications.page_title')}
                    actions={
                        notifications.data.length > 0 ? (
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={http.processing}
                                onClick={() => void markAll()}
                            >
                                {t('core::public.notifications.mark_all')}
                            </Button>
                        ) : undefined
                    }
                />

                {notifications.data.length === 0 ? (
                    <EmptyState
                        icon={Bell}
                        title={t('core::public.notifications.empty')}
                        description={t('core::public.notifications.empty_text')}
                    />
                ) : (
                    <ul className="divide-y divide-border overflow-hidden rounded-lg border border-border bg-card">
                        {notifications.data.map((item) => {
                            const unread = item.read_at === null;
                            const body = (
                                <>
                                    <span className="flex items-center gap-2">
                                        {unread && (
                                            <span
                                                aria-hidden
                                                className="size-2 shrink-0 rounded-full bg-primary"
                                            />
                                        )}
                                        <span
                                            className={cn(
                                                unread && 'font-medium',
                                            )}
                                        >
                                            {t(item.title, item.params)}
                                        </span>
                                        {unread && (
                                            <span className="sr-only">
                                                {t(
                                                    'core::public.notifications.unread',
                                                )}
                                            </span>
                                        )}
                                    </span>
                                    <time
                                        dateTime={item.created_at}
                                        className="text-xs text-muted-foreground"
                                    >
                                        {relativeTime(item.created_at, locale)}
                                    </time>
                                </>
                            );
                            const classes = cn(
                                'flex flex-col gap-0.5 px-4 py-3 text-sm',
                                unread && 'bg-accent/40',
                            );

                            return (
                                <li key={item.id}>
                                    {item.url !== null ? (
                                        <Link
                                            href={item.url}
                                            className={cn(
                                                classes,
                                                'outline-none hover:bg-accent focus-visible:bg-accent',
                                            )}
                                        >
                                            {body}
                                        </Link>
                                    ) : (
                                        <div className={classes}>{body}</div>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}

                <DataTablePagination
                    pagination={notifications.meta}
                    currentUrl={url}
                />
            </div>
        </>
    );
}
