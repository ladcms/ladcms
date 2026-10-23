import { useSyncExternalStore } from 'react';

/** Подписка на `matchMedia`; без `window.matchMedia` (SSR/jsdom) возвращает `fallback`. */
export function useMediaQuery(query: string, fallback = false): boolean {
    const supported =
        typeof window !== 'undefined' &&
        typeof window.matchMedia === 'function';

    return useSyncExternalStore(
        (callback) => {
            if (!supported) {
                return () => {};
            }
            const list = window.matchMedia(query);
            list.addEventListener('change', callback);

            return () => list.removeEventListener('change', callback);
        },
        () => (supported ? window.matchMedia(query).matches : fallback),
        () => fallback,
    );
}
