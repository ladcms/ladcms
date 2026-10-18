import { useHttp, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Badge } from '@ladcms/core/components/ui/badge';
import { Button } from '@ladcms/core/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@ladcms/core/components/ui/popover';
import { useT } from '@ladcms/core/lib/i18n';
import type { Translate } from '@ladcms/core/lib/i18n';
import { cn } from '@ladcms/core/lib/utils';
import { index, read, readAll } from '@/routes/notifications';

export type NotificationItem = {
    id: string;
    type: string;
    data: Record<string, unknown>;
    read_at: string | null;
    created_at: string;
};

type NotificationsPayload = {
    unread_count: number;
    notifications: NotificationItem[];
};

const POLL_INTERVAL_MS = 60_000;

/** Заголовок: `data.title`, иначе перевод по типу (`notifications.types.<ClassBasename>`), иначе сам тип. */
function titleOf(item: NotificationItem, t: Translate): string {
    const title = item.data.title;
    if (typeof title === 'string' && title !== '') {
        return title;
    }

    const key = `core::admin.notifications.types.${item.type}`;
    const translated = t(key);

    return translated === key ? item.type : translated;
}

function messageOf(item: NotificationItem): string | null {
    const message = item.data.message;

    return typeof message === 'string' && message !== '' ? message : null;
}

const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60],
];

function relativeTime(iso: string, locale: string): string {
    const timestamp = new Date(iso).getTime();
    if (Number.isNaN(timestamp)) {
        return '';
    }

    const formatter = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
    const seconds = Math.round((timestamp - Date.now()) / 1000);

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return formatter.format(Math.round(seconds / size), unit);
        }
    }

    return formatter.format(0, 'second');
}

/** Колокольчик уведомлений: не зависит от admin-пропсов, пригоден для публичной шапки. */
export function NotificationBell({ className }: { className?: string }) {
    const t = useT();
    const { locale } = usePage<{ locale?: string }>().props;
    const [items, setItems] = useState<NotificationItem[]>([]);
    const [unread, setUnread] = useState(0);
    const [failed, setFailed] = useState(false);
    const loader = useHttp<Record<string, never>, NotificationsPayload>(
        'get',
        index().url,
        {},
    );
    const mutator = useHttp<Record<string, never>, unknown>(
        'post',
        readAll().url,
        {},
    );
    // Хуки пересоздаются на каждый рендер — держим актуальные ссылки в ref, чтобы эффект polling не перезапускался.
    const loaderRef = useRef(loader);
    loaderRef.current = loader;

    // Устаревший ответ polling не должен затирать более свежее состояние: применяется только последний запрос.
    const sequence = useRef(0);

    const load = useCallback(async () => {
        const current = ++sequence.current;
        try {
            const payload = await loaderRef.current.get(index().url);
            if (current !== sequence.current) {
                return;
            }
            setItems(payload.notifications);
            setUnread(payload.unread_count);
            setFailed(false);
        } catch {
            if (current === sequence.current) {
                setFailed(true);
            }
        }
    }, []);

    useEffect(() => {
        void load();

        let timer: ReturnType<typeof setInterval> | null = null;
        const start = () => {
            if (timer === null) {
                timer = setInterval(() => void load(), POLL_INTERVAL_MS);
            }
        };
        const stop = () => {
            if (timer !== null) {
                clearInterval(timer);
                timer = null;
            }
        };
        const onVisibility = () => {
            if (document.hidden) {
                stop();
            } else {
                void load();
                start();
            }
        };

        if (!document.hidden) {
            start();
        }
        document.addEventListener('visibilitychange', onVisibility);

        return () => {
            stop();
            document.removeEventListener('visibilitychange', onVisibility);
        };
    }, [load]);

    const markRead = async (item: NotificationItem) => {
        if (item.read_at !== null) {
            return;
        }

        setItems((current) =>
            current.map((entry) =>
                entry.id === item.id
                    ? { ...entry, read_at: new Date().toISOString() }
                    : entry,
            ),
        );
        setUnread((count) => Math.max(0, count - 1));

        try {
            await mutator.post(read(item.id).url);
        } catch {
            // Состояние сверяет load() ниже.
        }
        void load();
    };

    const markAllRead = async () => {
        const now = new Date().toISOString();
        setItems((current) =>
            current.map((entry) => ({
                ...entry,
                read_at: entry.read_at ?? now,
            })),
        );
        setUnread(0);

        try {
            await mutator.post(readAll().url);
        } catch {
            // Состояние сверяет load() ниже.
        }
        void load();
    };

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className={cn('relative', className)}
                    aria-label={
                        unread > 0
                            ? t('core::admin.notifications.aria_unread', {
                                  count: unread,
                              })
                            : t('core::admin.notifications.aria')
                    }
                >
                    <Bell aria-hidden />
                    {unread > 0 && (
                        <Badge
                            aria-hidden
                            className="absolute -top-0.5 -right-0.5 h-4 min-w-4 justify-center px-1 text-[10px] leading-none"
                        >
                            {unread > 9 ? '9+' : unread}
                        </Badge>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="end"
                className="w-[min(22rem,calc(100vw-2rem))] p-0"
            >
                <div className="flex items-center justify-between gap-2 border-b border-border px-3 py-2">
                    <span className="text-sm font-medium">
                        {t('core::admin.notifications.title')}
                    </span>
                    <Button
                        variant="ghost"
                        size="sm"
                        disabled={unread === 0}
                        onClick={() => void markAllRead()}
                    >
                        {t('core::admin.notifications.mark_all')}
                    </Button>
                </div>
                {failed && (
                    <p
                        role="alert"
                        className="px-3 py-2 text-xs text-destructive"
                    >
                        {t('core::admin.notifications.load_error')}
                    </p>
                )}
                {items.length === 0 ? (
                    <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                        {t('core::admin.notifications.empty')}
                    </p>
                ) : (
                    <ul className="max-h-96 divide-y divide-border overflow-y-auto">
                        {items.map((item) => {
                            const message = messageOf(item);
                            const isUnread = item.read_at === null;

                            return (
                                <li key={item.id}>
                                    <button
                                        type="button"
                                        onClick={() => void markRead(item)}
                                        className={cn(
                                            'flex w-full flex-col gap-0.5 px-3 py-2 text-left text-sm outline-none hover:bg-accent focus-visible:bg-accent',
                                            isUnread && 'bg-accent/40',
                                        )}
                                    >
                                        <span className="flex items-center gap-2">
                                            {isUnread && (
                                                <span
                                                    aria-hidden
                                                    className="size-2 shrink-0 rounded-full bg-primary"
                                                />
                                            )}
                                            <span
                                                className={cn(
                                                    isUnread && 'font-medium',
                                                )}
                                            >
                                                {titleOf(item, t)}
                                            </span>
                                        </span>
                                        {message !== null && (
                                            <span className="line-clamp-2 text-muted-foreground">
                                                {message}
                                            </span>
                                        )}
                                        <time
                                            dateTime={item.created_at}
                                            className="text-xs text-muted-foreground"
                                        >
                                            {relativeTime(
                                                item.created_at,
                                                locale ?? 'en',
                                            )}
                                        </time>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </PopoverContent>
        </Popover>
    );
}
