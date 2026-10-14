import { createInertiaApp } from '@inertiajs/react';
import type { ResolvedComponent } from '@inertiajs/react';
import { resolveModulePage } from '@ladcms/core/inertia/resolve-page';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const appPages = import.meta.glob('./pages/**/*.tsx');
const modulePages = import.meta.glob(
    '/vendor/ladcms/*/resources/js/pages/**/*.tsx',
);

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    // Резолвер отдаёт модуль страницы ({ default }); Inertia сам разворачивает `module.default`.
    resolve: (name) =>
        resolveModulePage(
            name,
            appPages,
            modulePages,
        ) as Promise<ResolvedComponent>,
    layout: (name) => {
        switch (true) {
            case name === 'core::welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
