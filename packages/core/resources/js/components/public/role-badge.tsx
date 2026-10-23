import { useT } from '@ladcms/core/lib/i18n';
import type { Translate } from '@ladcms/core/lib/i18n';
import { cn } from '@ladcms/core/lib/utils';

/** Приоритет ролей для отображения: чем раньше, тем «выше». */
const ROLE_PRIORITY = [
    'creator',
    'main_admin',
    'admin',
    'super_moderator',
    'moderator',
    'vip',
];

export const ROLE_COLOR: Record<string, string> = {
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

export function publicRoleName(t: Translate, name: string): string {
    const key = `core::public.roles.${name}`;
    const label = t(key);

    return label === key ? name : label;
}

export type RoleInfo = { name: string; color: string | null };

const HEX_COLOR = /^#[0-9a-f]{6}$/i;

/** Название старшей из ролей её цветом (настроенный в админке hex, иначе цвет по умолчанию). */
export function RoleBadge({
    roles,
    className,
}: {
    roles: RoleInfo[];
    className?: string;
}) {
    const t = useT();
    const name = topRole(roles.map((role) => role.name));

    if (name === null) {
        return null;
    }

    const color = roles.find((role) => role.name === name)?.color ?? null;
    const custom = color !== null && HEX_COLOR.test(color) ? color : null;

    return (
        <span
            className={cn(
                'truncate text-xs',
                custom === null &&
                    (ROLE_COLOR[name] ?? 'text-muted-foreground'),
                className,
            )}
            style={custom === null ? undefined : { color: custom }}
        >
            {publicRoleName(t, name)}
        </span>
    );
}
