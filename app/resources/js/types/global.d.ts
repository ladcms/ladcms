import type { AdminShared } from '@ladcms/core/types/admin';
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
            [key: string]: unknown;
        };
    }
}
