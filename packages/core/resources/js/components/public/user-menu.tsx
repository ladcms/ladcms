import { Link, router, usePage } from '@inertiajs/react';
import {
    Avatar,
    AvatarFallback,
    AvatarImage,
} from '@ladcms/core/components/ui/avatar';
import { Button } from '@ladcms/core/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@ladcms/core/components/ui/dropdown-menu';
import { RoleBadge } from '@ladcms/core/components/public/role-badge';
import { resolveIcon } from '@ladcms/core/lib/icons';
import { initials } from '@ladcms/core/lib/initials';
import { useT } from '@ladcms/core/lib/i18n';
import type { CurrentUser } from '@ladcms/core/types/admin';
import type { LayoutShared } from '@ladcms/core/types/layout';
import { login, logout, register } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { profile as settingsProfile } from '@/routes/settings';
import { show as userProfile } from '@/routes/users';

export function UserMenu() {
    const t = useT();
    const { auth, layout } = usePage<{
        auth: { user: CurrentUser | null };
        layout?: LayoutShared | null;
    }>().props;
    const user = auth?.user;
    const UserIcon = resolveIcon('user');
    const SettingsIcon = resolveIcon('settings');
    const AdminIcon = resolveIcon('shield');
    const LogOutIcon = resolveIcon('log-out');

    if (!user) {
        return (
            <div className="flex items-center gap-2">
                <Button asChild variant="ghost" size="sm">
                    <Link href={login()}>
                        {t('core::public.user_menu.login')}
                    </Link>
                </Button>
                {layout?.site.registration_open && (
                    <Button asChild size="sm">
                        <Link href={register()}>
                            {t('core::public.user_menu.register')}
                        </Link>
                    </Button>
                )}
            </div>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    className="h-auto gap-2 rounded-full p-1 sm:pr-3"
                    aria-label={t('core::public.user_menu.menu')}
                >
                    <Avatar className="size-8">
                        {user.avatar_url && (
                            <AvatarImage
                                src={user.avatar_url}
                                alt={user.display_name}
                            />
                        )}
                        <AvatarFallback>
                            {initials(user.display_name)}
                        </AvatarFallback>
                    </Avatar>
                    <span className="hidden max-w-32 flex-col items-start text-left leading-tight sm:flex">
                        <span className="w-full truncate text-sm font-medium">
                            {user.display_name}
                        </span>
                        <RoleBadge roles={user.roles} className="w-full" />
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel className="font-normal">
                    <div className="grid gap-0.5">
                        <span className="truncate font-medium">
                            {user.display_name}
                        </span>
                        <RoleBadge roles={user.roles} />
                    </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuItem asChild>
                        <Link href={userProfile(user.login)}>
                            <UserIcon aria-hidden />
                            {t('core::public.user_menu.profile')}
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link href={settingsProfile()}>
                            <SettingsIcon aria-hidden />
                            {t('core::public.user_menu.settings')}
                        </Link>
                    </DropdownMenuItem>
                    {user.permissions.includes('admin.access') && (
                        <DropdownMenuItem asChild>
                            <Link href={adminDashboard()}>
                                <AdminIcon aria-hidden />
                                {t('core::public.user_menu.admin')}
                            </Link>
                        </DropdownMenuItem>
                    )}
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link
                        href={logout()}
                        as="button"
                        className="w-full"
                        onClick={() => router.flushAll()}
                    >
                        <LogOutIcon aria-hidden />
                        {t('core::public.user_menu.logout')}
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
