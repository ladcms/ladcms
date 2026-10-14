/** Текущий пользователь (core CurrentUserResource + email_verified_at из HandleInertiaRequests). */
export type User = {
    id: number;
    login: string;
    name: string;
    display_name: string;
    email: string;
    avatar_url: string | null;
    roles: string[];
    permissions: string[];
    is_banned: boolean;
    can_write: boolean;
    email_verified_at: string | null;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
