/** Shared-проп `layout` (`SharePublicLayout`): данные публичного layout; `null` на `admin.*`. */
export type LayoutNavItem = {
    key: string;
    label: string;
    icon: string;
    url: string;
    order: number;
    active: boolean;
};

export type LayoutHeaderAction = {
    key: string;
    icon: string;
    /** `<module>::<Name>` → `resources/js/header-actions/<Name>.tsx`. */
    component: string;
    order: number;
};

export type LayoutWidget = {
    name: string;
    /** `<module>::<Name>` → `resources/js/widgets/<Name>.tsx`. */
    component: string;
    module: string;
    order: number;
    props: Record<string, unknown>;
};

export type LayoutSite = {
    name: string;
    /** Корень сайта без завершающего `/` (`config('app.url')`) — для абсолютных `og:url`/`og:image`. */
    url: string;
    description: string | null;
    logo_url: string | null;
    registration_open: boolean;
};

export type LayoutShared = {
    navigation: LayoutNavItem[];
    header_actions: LayoutHeaderAction[];
    widgets: {
        sidebar: LayoutWidget[];
        main: LayoutWidget[];
    };
    site: LayoutSite;
};

/** Shared-проп `i18n`: плоская карта переводов текущей локали. */
export type I18nMap = Record<string, string>;
