import { createInertiaApp } from '@inertiajs/react';
import type { ResolvedComponent } from '@inertiajs/react';
import { ComponentRegistryProvider } from '@ladcms/core/inertia/component-registry';
import AdminLayout from '@ladcms/core/layouts/admin-layout';
import PublicLayout from '@ladcms/core/layouts/public-layout';
import SettingsLayout from '@ladcms/core/layouts/settings-layout';
import { resolveModulePage } from '@ladcms/core/inertia/resolve-page';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@ladcms/core/hooks/use-appearance';
import { useDocumentLang } from '@ladcms/core/lib/use-document-lang';
import AuthLayout from '@/layouts/auth-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const appPages = import.meta.glob('./pages/**/*.tsx');
const modulePages = import.meta.glob(
    '/vendor/ladcms/*/resources/js/pages/**/*.tsx',
);
// Компоненты модулей (виджеты, секции профиля, иконки шапки) резолвятся лениво через `ComponentRegistryProvider`.
const moduleComponents = import.meta.glob(
    '/vendor/ladcms/*/resources/js/{widgets,profile,header-actions}/**/*.tsx',
);

// `<html lang>` следует за shared-пропом `locale` и при SPA-навигации (смена языка без перезагрузки).
function DocumentLang() {
    useDocumentLang();

    return null;
}

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
            case /^[a-z0-9-]+::admin\//.test(name):
                return AdminLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            // Страницы настроек core и kit-страница безопасности (`settings/security`) делят вкладки core.
            case name.startsWith('core::settings/'):
            case name.startsWith('settings/'):
                return [PublicLayout, SettingsLayout];
            default:
                return PublicLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <ComponentRegistryProvider registry={moduleComponents}>
                <TooltipProvider delayDuration={0}>
                    <DocumentLang />
                    {app}
                    <Toaster />
                </TooltipProvider>
            </ComponentRegistryProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
