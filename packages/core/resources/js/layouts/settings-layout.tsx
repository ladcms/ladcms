import type { ReactNode } from 'react';

/** Каркас страниц настроек (вкладки добавляются в Task 6); вложен в `PublicLayout`. */
export default function SettingsLayout({ children }: { children: ReactNode }) {
    return <div className="mx-auto w-full max-w-3xl">{children}</div>;
}
