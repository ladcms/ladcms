import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState, useSyncExternalStore } from 'react';
import { AdminHeader } from '@ladcms/core/components/admin/admin-header';
import { IconRail } from '@ladcms/core/components/admin/icon-rail';
import { SectionPanel } from '@ladcms/core/components/admin/section-panel';
import { useT } from '@ladcms/core/lib/i18n';
import type { AdminBreadcrumb, AdminShared } from '@ladcms/core/types/admin';

/** Задержка возврата панели к закреплённой секции после ухода курсора. */
const HOVER_LEAVE_DELAY = 150;

const finePointer =
    typeof window === 'undefined'
        ? undefined
        : window.matchMedia('(pointer: fine)');

function subscribeFinePointer(callback: () => void) {
    finePointer?.addEventListener('change', callback);

    return () => finePointer?.removeEventListener('change', callback);
}

function useFinePointer(): boolean {
    return useSyncExternalStore(
        subscribeFinePointer,
        () => finePointer?.matches ?? true,
        () => true,
    );
}

type AdminLayoutProps = {
    children: ReactNode;
    title?: string;
    breadcrumbs?: AdminBreadcrumb[];
};

export default function AdminLayout({
    children,
    title,
    breadcrumbs,
}: AdminLayoutProps) {
    const t = useT();
    const { admin } = usePage<{ admin?: AdminShared | null }>().props;
    const menu = admin?.menu ?? [];
    const isFine = useFinePointer();

    // Закреплённая секция = активная секция роута (иначе первая); тап на таче меняет её локально.
    const activeKey =
        menu.find((section) => section.active)?.key ?? menu[0]?.key ?? null;
    const [pinned, setPinned] = useState(activeKey);
    const [syncedActive, setSyncedActive] = useState(activeKey);
    const [hovered, setHovered] = useState<string | null>(null);
    const leaveTimer = useRef<number | null>(null);

    if (syncedActive !== activeKey) {
        setSyncedActive(activeKey);
        setPinned(activeKey);
        setHovered(null);
    }

    const cancelLeave = () => {
        if (leaveTimer.current !== null) {
            window.clearTimeout(leaveTimer.current);
            leaveTimer.current = null;
        }
    };

    const scheduleLeave = () => {
        cancelLeave();
        leaveTimer.current = window.setTimeout(() => {
            leaveTimer.current = null;
            setHovered(null);
        }, HOVER_LEAVE_DELAY);
    };

    useEffect(() => cancelLeave, []);

    const displayedKey = hovered ?? pinned;
    const displayed = menu.find((section) => section.key === displayedKey);
    // Заголовок и крошки могут быть ключами `<module>::admin.*` — незнакомая строка остаётся как есть.
    const heading = title ? t(title) : undefined;
    const crumbs = (breadcrumbs ?? (title ? [{ title }] : [])).map((crumb) => ({
        ...crumb,
        title: t(crumb.title),
    }));

    return (
        <div className="flex min-h-svh bg-background text-foreground">
            {heading && <Head title={heading} />}

            {menu.length > 0 && (
                <aside
                    className="sticky top-0 hidden h-svh shrink-0 lg:flex"
                    onMouseEnter={isFine ? cancelLeave : undefined}
                    onMouseLeave={isFine ? scheduleLeave : undefined}
                >
                    <IconRail
                        menu={menu}
                        displayedKey={displayedKey}
                        isFine={isFine}
                        onHover={(key) => {
                            cancelLeave();
                            setHovered(key);
                        }}
                        onSelect={(key) => {
                            setPinned(key);
                            setHovered(null);
                        }}
                    />
                    <SectionPanel section={displayed} />
                </aside>
            )}

            <div className="flex min-w-0 flex-1 flex-col">
                {admin && (
                    <AdminHeader
                        menu={menu}
                        user={admin.user}
                        breadcrumbs={crumbs}
                    />
                )}
                <main className="flex-1 p-6">{children}</main>
            </div>
        </div>
    );
}
