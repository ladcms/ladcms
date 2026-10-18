import type { AdminShared } from '@ladcms/core/types/admin';
import type { I18nMap, LayoutShared } from '@ladcms/core/types/layout';
import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            admin?: AdminShared | null;
            layout?: LayoutShared | null;
            i18n?: I18nMap | null;
            [key: string]: unknown;
        };
    }
}
