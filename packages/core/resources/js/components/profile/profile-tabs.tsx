import type { KeyboardEvent } from 'react';
import { useT } from '@ladcms/core/lib/i18n';
import { cn } from '@ladcms/core/lib/utils';
import type { ProfileTab } from '@ladcms/core/types/profile';

export const tabId = (key: string) => `profile-tab-${key}`;
export const panelId = 'profile-tabpanel';

/** Ряд вкладок профиля: на телефоне прокручивается по горизонтали, стрелки переключают вкладку. */
export function ProfileTabs({
    tabs,
    active,
    onSelect,
}: {
    tabs: ProfileTab[];
    active: string;
    onSelect: (key: string) => void;
}) {
    const t = useT();

    const onKeyDown = (
        event: KeyboardEvent<HTMLButtonElement>,
        index: number,
    ) => {
        let target: number;
        switch (event.key) {
            case 'ArrowRight':
                target = (index + 1) % tabs.length;
                break;
            case 'ArrowLeft':
                target = (index - 1 + tabs.length) % tabs.length;
                break;
            case 'Home':
                target = 0;
                break;
            case 'End':
                target = tabs.length - 1;
                break;
            default:
                return;
        }

        event.preventDefault();
        const next = tabs[target];
        if (next) {
            onSelect(next.key);
            document.getElementById(tabId(next.key))?.focus();
        }
    };

    return (
        <div
            role="tablist"
            aria-label={t('core::public.profile.tabs.label')}
            className="flex [scrollbar-width:none] gap-1 overflow-x-auto overflow-y-hidden border-b px-2 [&::-webkit-scrollbar]:hidden"
        >
            {tabs.map((tab, index) => {
                const selected = tab.key === active;

                return (
                    <button
                        key={tab.key}
                        id={tabId(tab.key)}
                        type="button"
                        role="tab"
                        aria-selected={selected}
                        aria-controls={panelId}
                        tabIndex={selected ? 0 : -1}
                        onClick={() => onSelect(tab.key)}
                        onKeyDown={(event) => onKeyDown(event, index)}
                        className={cn(
                            '-mb-px shrink-0 border-b-2 px-4 py-2.5 text-sm font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            selected
                                ? 'border-primary text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {tab.label}
                    </button>
                );
            })}
        </div>
    );
}
