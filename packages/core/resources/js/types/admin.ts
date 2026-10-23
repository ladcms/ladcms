/** Flash-тост (`Inertia::flash('toast', [...])`), его показывает глобальный Toaster. */
export type { FlashToast as Toast } from '@ladcms/core/hooks/use-flash-toast';
export type { Paginator } from '@ladcms/core/types/table';

/** Пункт меню админки (`Ladcms\Core\Admin\MenuItem::toArray()`). */
export type MenuItem = {
    key: string;
    label: string;
    /** Имя иконки lucide в kebab-case (`layout-dashboard`), см. `lib/icons.ts`. */
    icon: string;
    route: string;
    url: string;
    order: number;
    permission: string | null;
    badge: number | null;
    active: boolean;
    children: MenuItem[];
};

/** Текущий пользователь (`CurrentUserResource`). */
export type CurrentUser = {
    id: number;
    login: string;
    name: string;
    display_name: string;
    email: string;
    avatar_url: string | null;
    roles: { name: string; color: string | null }[];
    permissions: string[];
    level: number;
    is_creator: boolean;
    is_banned: boolean;
    can_write: boolean;
};

/** Shared-проп `admin` (только на роутах `admin.*`). */
export type AdminShared = {
    menu: MenuItem[];
    user: CurrentUser;
};

/** Shared-проп `i18n` (только на роутах `admin.*`): плоская карта переводов текущей локали. */
export type AdminI18n = Record<string, string>;

export type AdminBreadcrumb = {
    title: string;
    href?: string;
};
