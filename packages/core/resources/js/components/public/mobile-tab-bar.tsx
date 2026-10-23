import { Link, usePage } from '@inertiajs/react';
import { Ellipsis } from 'lucide-react';
import { useState } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@ladcms/core/components/ui/sheet';
import { resolveIcon } from '@ladcms/core/lib/icons';
import { useT } from '@ladcms/core/lib/i18n';
import { cn } from '@ladcms/core/lib/utils';
import type { CurrentUser } from '@ladcms/core/types/admin';
import type { LayoutShared } from '@ladcms/core/types/layout';
import { login } from '@/routes';
import { show as userProfile } from '@/routes/users';

const tabClass =
    'flex h-12 flex-1 items-center justify-center text-muted-foreground transition-colors hover:text-foreground focus-visible:text-foreground focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring focus-visible:outline-none';

/** Сколько пунктов навигации помещается в панель вместе с «Я» (4); при большем числе последний слот — «Ещё». */
const MAX_ITEMS = 4;

/**
 * Нижняя панель телефона/планшета: только иконки с `aria-label`. До 4 пунктов навигации + «Я»;
 * если пунктов больше — первые 3, «Ещё» (Sheet с остальными) и «Я».
 */
export function MobileTabBar() {
    const t = useT();
    const [moreOpen, setMoreOpen] = useState(false);
    const { props, url } = usePage<{
        auth: { user: CurrentUser | null };
        layout?: LayoutShared | null;
    }>();
    const { auth, layout } = props;
    const user = auth?.user;
    const navigation = layout?.navigation ?? [];
    const overflow = navigation.length > MAX_ITEMS;
    const items = overflow ? navigation.slice(0, MAX_ITEMS - 1) : navigation;
    const rest = overflow ? navigation.slice(MAX_ITEMS - 1) : [];
    const restActive = rest.some((item) => item.active);
    const MeIcon = resolveIcon(user ? 'user' : 'log-in');
    const meLabel = user
        ? t('core::public.tabbar.me')
        : t('core::public.user_menu.login');
    const profileActive =
        !!user && url.split(/[?#]/)[0] === userProfile.url(user.login);

    return (
        <nav
            aria-label={t('core::public.tabbar.label')}
            className="fixed inset-x-0 bottom-0 z-30 flex border-t border-border bg-card pb-[env(safe-area-inset-bottom)] lg:hidden"
        >
            {items.map((item) => {
                const Icon = resolveIcon(item.icon);

                return (
                    <Link
                        key={item.key}
                        href={item.url}
                        aria-label={item.label}
                        aria-current={item.active ? 'page' : undefined}
                        className={cn(tabClass, item.active && 'text-primary')}
                    >
                        <Icon aria-hidden className="size-6" />
                    </Link>
                );
            })}
            {overflow && (
                <>
                    <button
                        type="button"
                        aria-label={t('core::public.tabbar.more')}
                        aria-haspopup="dialog"
                        aria-expanded={moreOpen}
                        onClick={() => setMoreOpen(true)}
                        className={cn(tabClass, restActive && 'text-primary')}
                    >
                        <Ellipsis aria-hidden className="size-6" />
                    </button>
                    <Sheet open={moreOpen} onOpenChange={setMoreOpen}>
                        <SheetContent side="bottom">
                            <SheetHeader>
                                <SheetTitle>
                                    {t('core::public.tabbar.more_title')}
                                </SheetTitle>
                                <SheetDescription className="sr-only">
                                    {t('core::public.tabbar.more_title')}
                                </SheetDescription>
                            </SheetHeader>
                            <ul className="flex flex-col gap-1 px-4 pb-6">
                                {rest.map((item) => {
                                    const Icon = resolveIcon(item.icon);

                                    return (
                                        <li key={item.key}>
                                            <Link
                                                href={item.url}
                                                aria-current={
                                                    item.active
                                                        ? 'page'
                                                        : undefined
                                                }
                                                onClick={() =>
                                                    setMoreOpen(false)
                                                }
                                                className={cn(
                                                    'flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                                    item.active &&
                                                        'bg-accent text-accent-foreground',
                                                )}
                                            >
                                                <Icon
                                                    aria-hidden
                                                    className="size-5"
                                                />
                                                {item.label}
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        </SheetContent>
                    </Sheet>
                </>
            )}
            <Link
                href={user ? userProfile(user.login) : login()}
                aria-label={meLabel}
                aria-current={profileActive ? 'page' : undefined}
                className={cn(tabClass, profileActive && 'text-primary')}
            >
                <MeIcon aria-hidden className="size-6" />
            </Link>
        </nav>
    );
}
