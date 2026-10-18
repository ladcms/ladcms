import { Link } from '@inertiajs/react';
import { Badge } from '@ladcms/core/components/ui/badge';
import { cn } from '@ladcms/core/lib/utils';
import type { MenuItem } from '@ladcms/core/types/admin';

/** Пункты секции; секция без подпунктов показывается как единственная ссылка. */
export function SectionLinks({
    section,
    onNavigate,
}: {
    section: MenuItem;
    onNavigate?: () => void;
}) {
    const items = section.children.length > 0 ? section.children : [section];

    return (
        <ul className="flex flex-col gap-0.5">
            {items.map((item) => (
                <li key={`${item.key}:${item.url}`}>
                    <Link
                        href={item.url}
                        aria-current={item.active ? 'page' : undefined}
                        onClick={onNavigate}
                        className={cn(
                            'flex h-9 items-center gap-2 rounded-md px-3 text-sm text-sidebar-foreground/80 transition-colors outline-none hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2 focus-visible:ring-sidebar-ring',
                            item.active &&
                                'bg-sidebar-accent font-medium text-sidebar-accent-foreground',
                        )}
                    >
                        <span className="truncate">{item.label}</span>
                        {item.badge ? (
                            <Badge variant="secondary" className="ml-auto">
                                {item.badge}
                            </Badge>
                        ) : null}
                    </Link>
                </li>
            ))}
        </ul>
    );
}

export function SectionPanel({ section }: { section: MenuItem | undefined }) {
    if (!section) {
        return null;
    }

    return (
        <nav
            aria-label={section.label}
            className="flex w-56 shrink-0 flex-col border-r border-sidebar-border bg-sidebar"
        >
            <div className="flex h-14 items-center px-4">
                <h2 className="truncate text-sm font-semibold text-sidebar-foreground">
                    {section.label}
                </h2>
            </div>
            <div className="flex-1 overflow-y-auto px-2 pb-4">
                <SectionLinks section={section} />
            </div>
        </nav>
    );
}
