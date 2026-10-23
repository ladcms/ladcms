import { Link } from '@inertiajs/react';
import { Ellipsis, Pencil, ShieldCheck } from 'lucide-react';
import { ModuleSlot } from '@ladcms/core/components/public/module-slot';
import { RoleBadge } from '@ladcms/core/components/public/role-badge';
import {
    Avatar,
    AvatarFallback,
    AvatarImage,
} from '@ladcms/core/components/ui/avatar';
import { Badge } from '@ladcms/core/components/ui/badge';
import { Button } from '@ladcms/core/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@ladcms/core/components/ui/dropdown-menu';
import { useComponentRegistry } from '@ladcms/core/inertia/component-registry';
import { resolveModuleComponent } from '@ladcms/core/inertia/resolve-component';
import { useT } from '@ladcms/core/lib/i18n';
import { initials } from '@ladcms/core/lib/initials';
import type {
    Profile,
    ProfileAction,
    ProfileModeration,
} from '@ladcms/core/types/profile';
import { profile as settingsProfile } from '@/routes/settings';

/** Шапка профиля: обложка, аватар, имя, роль, @login, статусы, счётчики, действия модулей и меню `⋯`. */
export function ProfileHeader({
    profile,
    actions,
    moderation,
    onSelectTab,
}: {
    profile: Profile;
    actions: ProfileAction[];
    moderation: ProfileModeration;
    onSelectTab: (key: string) => void;
}) {
    const t = useT();
    const registry = useComponentRegistry();
    const hasModerationMenu =
        moderation.can_ban ||
        moderation.can_assign_roles ||
        moderation.can_mark_suspicious ||
        moderation.admin_url !== null;

    return (
        <header>
            <div
                aria-hidden
                className="h-24 bg-gradient-to-r from-primary/30 via-primary/15 to-accent sm:h-32"
            />
            <div className="px-4 pb-4 sm:px-6">
                <div className="-mt-10 flex flex-wrap items-end gap-4 sm:-mt-12">
                    <Avatar className="size-20 border-4 border-card sm:size-24">
                        {profile.avatar_url && (
                            <AvatarImage src={profile.avatar_url} alt="" />
                        )}
                        <AvatarFallback className="text-xl">
                            {initials(profile.display_name)}
                        </AvatarFallback>
                    </Avatar>
                    <div className="ml-auto flex flex-wrap items-center gap-2 pb-1">
                        {actions.map((action) => {
                            const Action = resolveModuleComponent(
                                'profile',
                                action.component,
                                registry,
                            );

                            return Action ? (
                                <ModuleSlot key={action.key} fallback={null}>
                                    <Action profile={profile} />
                                </ModuleSlot>
                            ) : null;
                        })}
                        {moderation.can_edit && (
                            <Button variant="outline" asChild>
                                <Link href={settingsProfile()}>
                                    <Pencil aria-hidden />
                                    {t('core::public.profile.edit')}
                                </Link>
                            </Button>
                        )}
                        {hasModerationMenu && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        aria-label={t(
                                            'core::public.profile.more',
                                        )}
                                    >
                                        <Ellipsis aria-hidden />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    {moderation.admin_url && (
                                        <DropdownMenuItem asChild>
                                            <Link href={moderation.admin_url}>
                                                <ShieldCheck aria-hidden />
                                                {t(
                                                    'core::public.profile.admin_link',
                                                )}
                                            </Link>
                                        </DropdownMenuItem>
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>
                </div>

                <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1">
                    <h1 className="text-2xl font-bold tracking-tight break-words">
                        {profile.display_name}
                    </h1>
                    <RoleBadge roles={profile.roles} className="text-sm" />
                    {profile.is_suspicious === true && (
                        <Badge variant="outline">
                            {t('core::public.profile.suspicious')}
                        </Badge>
                    )}
                    {profile.is_banned && (
                        <Badge variant="destructive">
                            {t('core::public.profile.banned')}
                        </Badge>
                    )}
                </div>
                <div className="mt-1 flex items-center gap-2 text-sm text-muted-foreground">
                    <span>@{profile.login}</span>
                    {profile.is_online && (
                        <span className="inline-flex items-center gap-1">
                            <span
                                aria-hidden
                                className="size-2 rounded-full bg-emerald-500"
                            />
                            {t('core::public.profile.online')}
                        </span>
                    )}
                </div>

                {profile.counters.length > 0 && (
                    <ul
                        aria-label={t('core::public.profile.counters_label')}
                        className="mt-4 flex flex-wrap gap-x-6 gap-y-2"
                    >
                        {profile.counters.map((counter) => {
                            const body = (
                                <>
                                    <span className="font-semibold">
                                        {counter.value}
                                    </span>{' '}
                                    <span className="text-sm text-muted-foreground">
                                        {counter.label}
                                    </span>
                                </>
                            );
                            const tab = counter.tab;

                            return (
                                <li key={counter.key}>
                                    {tab ? (
                                        <button
                                            type="button"
                                            className="rounded hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            onClick={() => onSelectTab(tab)}
                                        >
                                            {body}
                                        </button>
                                    ) : (
                                        body
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </header>
    );
}
