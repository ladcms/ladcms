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
            const text = i18n?.[key] ?? key;
            if (!replacements) {
                return text;
            }

            // Один проход: значение, содержащее `:другой`, повторно не подставляется.
            return text.replace(/:([A-Za-z_][A-Za-z0-9_]*)/g, (match, name) =>
                Object.hasOwn(replacements, name)
                    ? String(replacements[name])
                    : match,
            );
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
