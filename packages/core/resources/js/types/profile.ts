import type { RoleInfo } from '@ladcms/core/components/public/role-badge';

export type ProfileCounter = {
    key: string;
    label: string;
    value: number | string;
    /** Ключ вкладки, на которую ведёт счётчик. */
    tab?: string;
};

/** `ProfileResource`: `email` / `birth_date` приходят только по правилам приватности. */
export type Profile = {
    id: number;
    login: string;
    name: string | null;
    display_name: string;
    avatar_url: string | null;
    roles: RoleInfo[];
    is_online: boolean;
    created_at: string | null;
    email?: string;
    birth_date?: string;
    about: string | null;
    signature: string | null;
    sex: 'male' | 'female' | null;
    language: string;
    last_activity_at: string | null;
    is_banned: boolean;
    /** Только для зрителей с `users.view`. */
    is_suspicious?: boolean;
    counters: ProfileCounter[];
};

export type ProfileTab = {
    key: string;
    label: string;
    /** `<module>::<Name>` → `resources/js/profile/<Name>.tsx`. */
    component: string;
};

export type ProfileAction = {
    key: string;
    label: string;
    icon: string;
    component: string;
    order: number;
};

export type OverviewBlock = {
    key: string;
    component: string;
    order: number;
};

export type ProfileModeration = {
    can_ban: boolean;
    can_assign_roles: boolean;
    can_mark_suspicious: boolean;
    can_edit: boolean;
    admin_url: string | null;
};
