import { setLayoutProps, usePage } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { useEffect } from 'react';
import {
    BanForm,
    SuspiciousForm,
} from '@ladcms/core/components/admin/users/ban-form';
import { BansHistory } from '@ladcms/core/components/admin/users/bans-history';
import { DangerZone } from '@ladcms/core/components/admin/users/danger-zone';
import { ProfileForm } from '@ladcms/core/components/admin/users/profile-form';
import { RolesForm } from '@ladcms/core/components/admin/users/roles-form';
import { PageHeader } from '@ladcms/core/components/page-header';
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@ladcms/core/components/ui/alert';
import {
    Avatar,
    AvatarFallback,
    AvatarImage,
} from '@ladcms/core/components/ui/avatar';
import { Badge } from '@ladcms/core/components/ui/badge';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@ladcms/core/components/ui/tabs';
import type { AdminBreadcrumb } from '@ladcms/core/types/admin';

const t = {
    title: 'Пользователь',
    users: 'Пользователи',
    error: 'Не удалось выполнить действие',
    registered: 'Регистрация',
    lastActivity: 'Активность',
    bans: 'Банов',
    complaints: 'Жалоб',
    statuses: {
        online: 'Онлайн',
        banned: 'Забанен',
        readonly: 'Только чтение',
        suspicious: 'Подозрительный',
        unverified: 'Email не подтверждён',
    },
    tabs: {
        profile: 'Профиль',
        roles: 'Роли',
        moderation: 'Модерация',
        danger: 'Опасная зона',
    },
};

/** `BanResource`. */
export type BanItem = {
    id: number;
    user: { id: number; login: string } | null;
    banned_by: { id: number; login: string } | null;
    type: 'full' | 'readonly';
    reason_code: string;
    comment: string | null;
    starts_at: string;
    expires_at: string | null;
    is_active: boolean;
};

/** `UserDetailResource`. */
export type UserDetail = {
    id: number;
    login: string;
    name: string;
    display_name: string;
    email: string;
    avatar_url: string | null;
    roles: { name: string; color: string | null; level: number | null }[];
    is_online: boolean;
    is_banned: boolean;
    is_suspicious: boolean;
    created_at: string | null;
    last_activity_at: string | null;
    sex: 'male' | 'female' | null;
    birth_date: string | null;
    about: string | null;
    signature: string | null;
    language: string;
    timezone: string;
    registered_ip: string | null;
    suspicious_note: string | null;
    email_verified_at: string | null;
    active_ban: BanItem | null;
    bans_count: number;
    complaints_count: number;
};

/** `RoleResource`. */
export type RoleItem = {
    id: number;
    name: string;
    level: number | null;
    color: string | null;
    is_default: boolean;
    is_system: boolean;
};

/** Код нарушения из `ModerationSettings::$violation_codes`. */
export type ViolationCode = {
    code: string;
    label: string;
    min_days: number;
    max_days: number | null;
};

export type UserAbilities = {
    update: boolean;
    assignRoles: boolean;
    ban: boolean;
    delete: boolean;
    markSuspicious: boolean;
};

type Props = {
    user: UserDetail;
    roles: RoleItem[];
    assignable_roles: string[];
    violation_codes: ViolationCode[];
    bans: BanItem[];
    can: UserAbilities;
};

type LayoutProps = { title: string; breadcrumbs: AdminBreadcrumb[] };

function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    const first = parts[0]?.[0] ?? '';
    const last = parts.length > 1 ? (parts[parts.length - 1]?.[0] ?? '') : '';

    return (first + last).toUpperCase() || '?';
}

export function useDateFormat(): (value: string | null) => string {
    const { props } = usePage();
    const locale = typeof props.locale === 'string' ? props.locale : 'ru';
    const format = new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

    return (value) => (value === null ? '—' : format.format(new Date(value)));
}

export default function UserShow({
    user,
    roles,
    assignable_roles,
    violation_codes,
    bans,
    can,
}: Props) {
    const { props } = usePage<{ errors: Record<string, string> }>();
    const general = props.errors.general;
    const formatDate = useDateFormat();

    useEffect(() => {
        setLayoutProps<LayoutProps>({
            title: `${t.title} ${user.login}`,
            breadcrumbs: [
                { title: t.users, href: '/admin/users' },
                { title: user.login },
            ],
        });
    }, [user.login]);

    return (
        <div className="space-y-6">
            <div className="flex items-start gap-4">
                <Avatar className="size-16">
                    {user.avatar_url && (
                        <AvatarImage
                            src={user.avatar_url}
                            alt={user.display_name}
                        />
                    )}
                    <AvatarFallback className="text-lg">
                        {initials(user.display_name)}
                    </AvatarFallback>
                </Avatar>
                <div className="min-w-0 flex-1 space-y-2">
                    <PageHeader
                        title={user.display_name}
                        description={`@${user.login} · ${user.email}`}
                    />
                    <div className="flex flex-wrap items-center gap-1.5">
                        {user.roles.map((role) => (
                            <Badge
                                key={role.name}
                                variant={role.color ? 'default' : 'secondary'}
                                style={
                                    role.color
                                        ? { backgroundColor: role.color }
                                        : undefined
                                }
                            >
                                {role.name}
                            </Badge>
                        ))}
                        {user.is_online && (
                            <Badge variant="outline">
                                <span
                                    aria-hidden
                                    className="size-2 rounded-full bg-emerald-500"
                                />
                                {t.statuses.online}
                            </Badge>
                        )}
                        {user.active_ban && (
                            <Badge variant="destructive">
                                {user.active_ban.type === 'readonly'
                                    ? t.statuses.readonly
                                    : t.statuses.banned}
                            </Badge>
                        )}
                        {user.is_suspicious && (
                            <Badge
                                variant="outline"
                                className="border-amber-500/50 text-amber-600 dark:text-amber-400"
                            >
                                {t.statuses.suspicious}
                            </Badge>
                        )}
                        {user.email_verified_at === null && (
                            <Badge variant="outline">
                                {t.statuses.unverified}
                            </Badge>
                        )}
                    </div>
                    <dl className="flex flex-wrap gap-x-6 gap-y-1 text-sm text-muted-foreground">
                        <div className="flex gap-1">
                            <dt>{t.registered}:</dt>
                            <dd>{formatDate(user.created_at)}</dd>
                        </div>
                        <div className="flex gap-1">
                            <dt>{t.lastActivity}:</dt>
                            <dd>{formatDate(user.last_activity_at)}</dd>
                        </div>
                        <div className="flex gap-1">
                            <dt>{t.bans}:</dt>
                            <dd>{user.bans_count}</dd>
                        </div>
                        <div className="flex gap-1">
                            <dt>{t.complaints}:</dt>
                            <dd>{user.complaints_count}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {general && (
                <Alert variant="destructive">
                    <AlertCircle aria-hidden />
                    <AlertTitle>{t.error}</AlertTitle>
                    <AlertDescription>{general}</AlertDescription>
                </Alert>
            )}

            <Tabs defaultValue="profile">
                <TabsList>
                    <TabsTrigger value="profile">{t.tabs.profile}</TabsTrigger>
                    <TabsTrigger value="roles">{t.tabs.roles}</TabsTrigger>
                    <TabsTrigger value="moderation">
                        {t.tabs.moderation}
                    </TabsTrigger>
                    {(can.update || can.delete) && (
                        <TabsTrigger value="danger">
                            {t.tabs.danger}
                        </TabsTrigger>
                    )}
                </TabsList>

                <TabsContent value="profile" className="pt-4">
                    <ProfileForm user={user} disabled={!can.update} />
                </TabsContent>

                <TabsContent value="roles" className="pt-4">
                    <RolesForm
                        user={user}
                        roles={roles}
                        assignable={assignable_roles}
                        disabled={!can.assignRoles}
                    />
                </TabsContent>

                <TabsContent value="moderation" className="space-y-6 pt-4">
                    {can.ban && <BanForm user={user} codes={violation_codes} />}
                    <BansHistory
                        user={user}
                        bans={bans}
                        codes={violation_codes}
                        canLift={can.ban}
                    />
                    <SuspiciousForm
                        user={user}
                        disabled={!can.markSuspicious}
                    />
                </TabsContent>

                {(can.update || can.delete) && (
                    <TabsContent value="danger" className="pt-4">
                        <DangerZone user={user} can={can} />
                    </TabsContent>
                )}
            </Tabs>
        </div>
    );
}

UserShow.layout = {
    title: t.title,
    breadcrumbs: [{ title: t.users, href: '/admin/users' }, { title: t.title }],
};
