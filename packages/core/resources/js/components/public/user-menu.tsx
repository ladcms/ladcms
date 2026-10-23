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
import { resolveIcon } from '@ladcms/core/lib/icons';
import { initials } from '@ladcms/core/lib/initials';
import { useT } from '@ladcms/core/lib/i18n';
import type { Translate } from '@ladcms/core/lib/i18n';
import { cn } from '@ladcms/core/lib/utils';
import type { CurrentUser } from '@ladcms/core/types/admin';
import type { LayoutShared } from '@ladcms/core/types/layout';
import { login, logout, register } from '@/routes';

/** Приоритет ролей для отображения: чем раньше, тем «выше». */
const ROLE_PRIORITY = [
    'creator',
    'main_admin',
    'admin',
    'super_moderator',
    'moderator',
    'vip',
];

const ROLE_COLOR: Record<string, string> = {
    creator: 'text-amber-600 dark:text-amber-400',
    main_admin: 'text-rose-600 dark:text-rose-400',
    admin: 'text-rose-600 dark:text-rose-400',
    super_moderator: 'text-violet-600 dark:text-violet-400',
    moderator: 'text-sky-600 dark:text-sky-400',
    vip: 'text-emerald-600 dark:text-emerald-400',
};

export function topRole(roles: string[]): string | null {
    for (const name of ROLE_PRIORITY) {
        if (roles.includes(name)) {
            return name;
        }
    }

    return roles.find((name) => name !== 'user' && name !== 'guest') ?? null;
}

function publicRoleName(t: Translate, name: string): string {
    const key = `core::public.roles.${name}`;
    const label = t(key);

    return label === key ? name : label;
}

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

    const role = topRole(user.roles);

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
                        {role && (
                            <span
                                className={cn(
                                    'w-full truncate text-xs',
                                    ROLE_COLOR[role] ?? 'text-muted-foreground',
                                )}
                            >
                                {publicRoleName(t, role)}
                            </span>
                        )}
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel className="font-normal">
                    <div className="grid gap-0.5">
                        <span className="truncate font-medium">
                            {user.display_name}
                        </span>
                        {role && (
                            <span
                                className={cn(
                                    'truncate text-xs',
                                    ROLE_COLOR[role] ?? 'text-muted-foreground',
                                )}
                            >
                                {publicRoleName(t, role)}
                            </span>
                        )}
                    </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuItem asChild>
                        <Link href={`/u/${user.login}`}>
                            <UserIcon aria-hidden />
                            {t('core::public.user_menu.profile')}
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link href="/settings/profile">
                            <SettingsIcon aria-hidden />
                            {t('core::public.user_menu.settings')}
                        </Link>
                    </DropdownMenuItem>
                    {user.permissions.includes('admin.access') && (
                        <DropdownMenuItem asChild>
                            <Link href="/admin">
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
