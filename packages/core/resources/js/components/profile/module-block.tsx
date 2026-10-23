import { ArrowRight } from 'lucide-react';
import { Button } from '@ladcms/core/components/ui/button';
import { Card } from '@ladcms/core/components/ui/card';
import { Skeleton } from '@ladcms/core/components/ui/skeleton';
import { ModuleSlot } from '@ladcms/core/components/public/module-slot';
import { useComponentRegistry } from '@ladcms/core/inertia/component-registry';
import { resolveModuleComponent } from '@ladcms/core/inertia/resolve-component';
import { useT } from '@ladcms/core/lib/i18n';
import type { OverviewBlock, Profile } from '@ladcms/core/types/profile';

/** Блок модуля на «Обзоре»; «Все →» (если у модуля есть вкладка) переключает на неё. */
export function ModuleBlock({
    block,
    profile,
    onShowAll,
}: {
    block: OverviewBlock;
    profile: Profile;
    onShowAll?: () => void;
}) {
    const t = useT();
    const registry = useComponentRegistry();
    const Block = resolveModuleComponent('profile', block.component, registry);

    if (!Block) {
        return null;
    }

    return (
        <Card className="gap-0 overflow-hidden py-0">
            <ModuleSlot fallback={<Skeleton className="h-24" />}>
                <Block profile={profile} />
            </ModuleSlot>
            {onShowAll && (
                <div className="border-t px-4 py-2">
                    <Button
                        variant="link"
                        size="sm"
                        className="px-0"
                        onClick={onShowAll}
                    >
                        {t('core::public.profile.block_all')}
                        <ArrowRight aria-hidden />
                    </Button>
                </div>
            )}
        </Card>
    );
}
