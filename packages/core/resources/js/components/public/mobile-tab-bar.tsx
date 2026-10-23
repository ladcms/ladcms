import { Link, usePage } from '@inertiajs/react';
import { resolveIcon } from '@ladcms/core/lib/icons';
import { useT } from '@ladcms/core/lib/i18n';
import { cn } from '@ladcms/core/lib/utils';
import type { CurrentUser } from '@ladcms/core/types/admin';
import type { LayoutShared } from '@ladcms/core/types/layout';
import { login } from '@/routes';

const tabClass =
    'flex h-12 flex-1 items-center justify-center text-muted-foreground transition-colors hover:text-foreground';

/** Нижняя панель телефона/планшета: первые 4 пункта навигации + «Я», только иконки с `aria-label`. */
export function MobileTabBar() {
    const t = useT();
    const { props, url } = usePage<{
        auth: { user: CurrentUser | null };
        layout?: LayoutShared | null;
    }>();
    const { auth, layout } = props;
    const user = auth?.user;
    const items = (layout?.navigation ?? []).slice(0, 4);
    const MeIcon = resolveIcon(user ? 'user' : 'log-in');
    const meLabel = user
        ? t('core::public.tabbar.me')
        : t('core::public.user_menu.login');
    const profileActive = !!user && url.split(/[?#]/)[0] === `/u/${user.login}`;

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
            <Link
                href={user ? `/u/${user.login}` : login()}
                aria-label={meLabel}
                aria-current={profileActive ? 'page' : undefined}
                className={cn(tabClass, profileActive && 'text-primary')}
            >
                <MeIcon aria-hidden className="size-6" />
            </Link>
        </nav>
    );
}
