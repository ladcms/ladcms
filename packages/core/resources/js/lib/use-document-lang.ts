import { router } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Синхронизирует `<html lang>` с shared-пропом `locale` при SPA-навигации: сервер выставляет `lang`
 * только при полной загрузке страницы, а смена языка (футер, настройки) приходит Inertia-визитом.
 */
export function useDocumentLang(): void {
    useEffect(
        () =>
            router.on('navigate', (event) => {
                const locale = event.detail.page.props.locale;

                if (typeof locale === 'string' && locale !== '') {
                    document.documentElement.lang = locale;
                }
            }),
        [],
    );
}
