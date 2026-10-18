import type { LucideIcon } from 'lucide-react';
import { Inbox } from 'lucide-react';
import type { ReactNode } from 'react';

type EmptyStateProps = {
    icon?: LucideIcon;
    title: ReactNode;
    description?: ReactNode;
};

export function EmptyState({
    icon: Icon = Inbox,
    title,
    description,
}: EmptyStateProps) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center">
            <div className="flex size-12 items-center justify-center rounded-full bg-accent text-accent-foreground">
                <Icon className="size-6" />
            </div>
            <div className="space-y-1">
                <p className="font-semibold">{title}</p>
                {description && (
                    <p className="max-w-sm text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
        </div>
    );
}
