import { Link } from '@inertiajs/react';
import type { MouseEvent } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@ladcms/core/components/ui/tooltip';
import { resolveIcon } from '@ladcms/core/lib/icons';
import { cn } from '@ladcms/core/lib/utils';
import type { MenuItem } from '@ladcms/core/types/admin';
import { useT } from '@ladcms/core/lib/i18n';

/** Куда ведёт клик по секции: первый дочерний пункт или сама секция. */
export function sectionTarget(section: MenuItem): string {
    return section.children[0]?.url ?? section.url;
}

type IconRailProps = {
    menu: MenuItem[];
    /** Секция, показанная сейчас в панели (hover ?? pinned). */
    displayedKey: string | null;
    /** `pointer: fine` — hover переключает панель; иначе работает только клик. */
    isFine: boolean;
    onHover: (key: string) => void;
    onSelect: (key: string) => void;
};

export function IconRail({
    menu,
    displayedKey,
    isFine,
    onHover,
    onSelect,
}: IconRailProps) {
    const t = useT();
    const handleClick = (event: MouseEvent, section: MenuItem) => {
        // Тач: тап по секции с подпунктами только открывает панель, без перехода.
        if (!isFine && section.children.length > 0) {
            event.preventDefault();
        }

        onSelect(section.key);
    };

    return (
        <nav
            aria-label={t('core::admin.rail.sections')}
            className="flex w-14 shrink-0 flex-col items-center gap-1 border-r border-sidebar-border bg-sidebar py-3"
        >
            {menu.map((section) => {
                const Icon = resolveIcon(section.icon);
                const displayed = section.key === displayedKey;

                return (
                    <Tooltip key={section.key}>
                        <TooltipTrigger asChild>
                            <Link
                                href={sectionTarget(section)}
                                aria-label={section.label}
                                aria-current={
                                    section.active ? 'true' : undefined
                                }
                                onMouseEnter={
                                    isFine
                                        ? () => onHover(section.key)
                                        : undefined
                                }
                                onClick={(event) => handleClick(event, section)}
                                className={cn(
                                    'relative flex size-10 items-center justify-center rounded-lg text-sidebar-foreground/70 transition-colors outline-none hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2 focus-visible:ring-sidebar-ring',
                                    displayed &&
                                        'bg-sidebar-accent text-sidebar-accent-foreground',
                                    section.active &&
                                        'bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary hover:text-sidebar-primary-foreground',
                                )}
                            >
                                <Icon className="size-5" aria-hidden />
                                {section.badge ? (
                                    <span
                                        aria-hidden
                                        className="absolute top-1.5 right-1.5 size-2 rounded-full bg-destructive"
                                    />
                                ) : null}
                            </Link>
                        </TooltipTrigger>
                        <TooltipContent side="right">
                            {section.label}
                            {section.badge ? ` (${section.badge})` : null}
                        </TooltipContent>
                    </Tooltip>
                );
            })}
        </nav>
    );
}
