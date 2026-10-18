import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';

export type Replacements = Record<string, string | number>;

export type Translate = (key: string, replacements?: Replacements) => string;

/**
 * Хук переводов админки. Карта `i18n` приходит shared-пропом на админ-роутах:
 * `{ "<module>::admin.<key>": "перевод" }` для текущей локали. Нет ключа — возвращается сам ключ.
 * Плейсхолдеры `:name` заменяются значениями из `replacements`.
 */
export function useT(): Translate {
    const { i18n } = usePage().props;

    // Стабильная ссылка: функцию безопасно указывать в зависимостях эффектов.
    return useCallback(
        (key, replacements) => {
            let text = i18n?.[key] ?? key;
            for (const [name, value] of Object.entries(replacements ?? {})) {
                text = text.replaceAll(`:${name}`, String(value));
            }

            return text;
        },
        [i18n],
    );
}

/** Название роли для интерфейса: `core::admin.roles.names.<name>`, иначе сырое имя (кастомные роли). */
export function roleName(t: Translate, name: string): string {
    const key = `core::admin.roles.names.${name}`;
    const label = t(key);

    return label === key ? name : label;
}
