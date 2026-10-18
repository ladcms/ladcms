import { Link, router } from '@inertiajs/react';
import { LogOut, Settings, User } from 'lucide-react';
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
import type { CurrentUser } from '@ladcms/core/types/admin';
import { roleName, useT } from '@ladcms/core/lib/i18n';

function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    const first = parts[0]?.[0] ?? '';
    const last = parts.length > 1 ? (parts[parts.length - 1]?.[0] ?? '') : '';

    return (first + last).toUpperCase() || '?';
}

export function UserMenu({ user }: { user: CurrentUser }) {
    const t = useT();
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={t('core::admin.user_menu.menu')}
                    className="rounded-full"
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
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel className="font-normal">
                    <div className="grid gap-0.5">
                        <span className="truncate font-medium">
                            {user.display_name}
                        </span>
                        {user.roles.length > 0 && (
                            <span className="truncate text-xs text-muted-foreground">
                                {user.roles
                                    .map((name) => roleName(t, name))
                                    .join(', ')}
                            </span>
                        )}
                    </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuItem asChild>
                        <Link href={`/u/${user.login}`}>
                            <User aria-hidden />
                            {t('core::admin.user_menu.profile')}
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link href="/settings/profile">
                            <Settings aria-hidden />
                            {t('core::admin.user_menu.settings')}
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="w-full"
                        onClick={() => router.flushAll()}
                    >
                        <LogOut aria-hidden />
                        {t('core::admin.user_menu.logout')}
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
