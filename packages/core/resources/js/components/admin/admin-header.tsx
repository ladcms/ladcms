import { Link } from '@inertiajs/react';
import { ChevronRight, Menu } from 'lucide-react';
import { useState } from 'react';
import { NotificationBell } from '@ladcms/core/components/notification-bell';
import { AdminBreadcrumbs } from '@ladcms/core/components/admin/admin-breadcrumbs';
import { SectionLinks } from '@ladcms/core/components/admin/section-panel';
import { UserMenu } from '@ladcms/core/components/admin/user-menu';
import { Button } from '@ladcms/core/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@ladcms/core/components/ui/sheet';
import { resolveIcon } from '@ladcms/core/lib/icons';
import { cn } from '@ladcms/core/lib/utils';
import type {
    AdminBreadcrumb,
    CurrentUser,
    MenuItem,
} from '@ladcms/core/types/admin';
import { useT } from '@ladcms/core/lib/i18n';

/** Мобильная навигация: секции аккордеоном, тап раскрывает подпункты. */
function MobileNav({
    menu,
    onNavigate,
}: {
    menu: MenuItem[];
    onNavigate: () => void;
}) {
    const t = useT();
    const [expanded, setExpanded] = useState<string | null>(
        () => menu.find((section) => section.active)?.key ?? null,
    );

    return (
        <nav
            aria-label={t('core::admin.header.menu')}
            className="flex-1 overflow-y-auto px-2 pb-4"
        >
            <ul className="flex flex-col gap-1">
                {menu.map((section) => {
                    const Icon = resolveIcon(section.icon);
                    const open = expanded === section.key;
                    const rowClass = cn(
                        'flex h-10 w-full items-center gap-3 rounded-md px-3 text-sm font-medium text-sidebar-foreground transition-colors outline-none hover:bg-sidebar-accent focus-visible:ring-2 focus-visible:ring-sidebar-ring',
                        section.active && 'text-sidebar-primary',
                    );

                    if (section.children.length === 0) {
                        return (
                            <li key={section.key}>
                                <Link
                                    href={section.url}
                                    aria-current={
                                        section.active ? 'page' : undefined
                                    }
                                    onClick={onNavigate}
                                    className={rowClass}
                                >
                                    <Icon className="size-5" aria-hidden />
                                    {section.label}
                                </Link>
                            </li>
                        );
                    }

                    return (
                        <li key={section.key}>
                            <button
                                type="button"
                                aria-expanded={open}
                                onClick={() =>
                                    setExpanded(open ? null : section.key)
                                }
                                className={rowClass}
                            >
                                <Icon className="size-5" aria-hidden />
                                {section.label}
                                <ChevronRight
                                    aria-hidden
                                    className={cn(
                                        'ml-auto size-4 transition-transform',
                                        open && 'rotate-90',
                                    )}
                                />
                            </button>
                            {open && (
                                <div className="mt-1 ml-8">
                                    <SectionLinks
                                        section={section}
                                        onNavigate={onNavigate}
                                    />
                                </div>
                            )}
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

type AdminHeaderProps = {
    menu: MenuItem[];
    user: CurrentUser;
    breadcrumbs: AdminBreadcrumb[];
};

export function AdminHeader({ menu, user, breadcrumbs }: AdminHeaderProps) {
    const t = useT();
    const [open, setOpen] = useState(false);

    return (
        <header className="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b border-border bg-background/95 px-4 backdrop-blur supports-[backdrop-filter]:bg-background/80 lg:px-6">
            <Sheet open={open} onOpenChange={setOpen}>
                <Button
                    variant="ghost"
                    size="icon"
                    className="lg:hidden"
                    aria-label={t('core::admin.header.open_menu')}
                    aria-expanded={open}
                    onClick={() => setOpen(true)}
                >
                    <Menu aria-hidden />
                </Button>
                <SheetContent side="left" className="w-72 gap-0 bg-sidebar p-0">
                    <SheetHeader>
                        <SheetTitle>{t('core::admin.header.menu')}</SheetTitle>
                        <SheetDescription className="sr-only">
                            {t('core::admin.header.menu_description')}
                        </SheetDescription>
                    </SheetHeader>
                    {/* Контент Sheet монтируется заново при каждом открытии — аккордеон стартует с активной секции. */}
                    <MobileNav menu={menu} onNavigate={() => setOpen(false)} />
                </SheetContent>
            </Sheet>

            <div className="min-w-0 flex-1">
                <AdminBreadcrumbs items={breadcrumbs} />
            </div>

            <div className="flex items-center gap-1">
                <NotificationBell />
                <UserMenu user={user} />
            </div>
        </header>
    );
}
