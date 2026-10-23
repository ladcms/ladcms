import { router } from '@inertiajs/react';
import { ModuleBlock } from '@ladcms/core/components/profile/module-block';
import { ProfileHeader } from '@ladcms/core/components/profile/profile-header';
import {
    ProfileTabs,
    panelId,
    tabId,
} from '@ladcms/core/components/profile/profile-tabs';
import { ModuleSlot } from '@ladcms/core/components/public/module-slot';
import { Card } from '@ladcms/core/components/ui/card';
import { Skeleton } from '@ladcms/core/components/ui/skeleton';
import { useComponentRegistry } from '@ladcms/core/inertia/component-registry';
import { resolveModuleComponent } from '@ladcms/core/inertia/resolve-component';
import { Seo } from '@ladcms/core/components/public/seo';
import type {
    OverviewBlock,
    Profile,
    ProfileAction,
    ProfileModeration,
    ProfileTab,
} from '@ladcms/core/types/profile';
import { show as userProfile } from '@/routes/users';

type Props = {
    profile: Profile;
    tabs: ProfileTab[];
    active_tab: string;
    overview_blocks: OverviewBlock[];
    actions: ProfileAction[];
    moderation: ProfileModeration;
    /** Только для вкладок модулей. */
    tab_data?: Record<string, unknown>;
};

const OVERVIEW = 'overview';

export default function UserShow({
    profile,
    tabs,
    active_tab,
    overview_blocks,
    actions,
    moderation,
    tab_data,
}: Props) {
    const registry = useComponentRegistry();
    const active = tabs.find((tab) => tab.key === active_tab) ?? tabs[0];

    const selectTab = (key: string) => {
        if (key === active_tab) {
            return;
        }

        router.get(
            userProfile.url(
                profile.login,
                key === OVERVIEW ? undefined : { query: { tab: key } },
            ),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['tab_data', 'active_tab'],
            },
        );
    };

    const TabComponent = active
        ? resolveModuleComponent('profile', active.component, registry)
        : null;

    return (
        <>
            <Seo
                title={profile.display_name}
                description={profile.about}
                image={profile.avatar_url}
                type="profile"
            />
            <div className="flex flex-col gap-4">
                <Card className="gap-0 overflow-hidden py-0">
                    <ProfileHeader
                        profile={profile}
                        actions={actions}
                        moderation={moderation}
                        onSelectTab={selectTab}
                    />
                    <ProfileTabs
                        tabs={tabs}
                        active={active_tab}
                        onSelect={selectTab}
                    />
                </Card>

                <div
                    id={panelId}
                    role="tabpanel"
                    aria-labelledby={active ? tabId(active.key) : undefined}
                >
                    {active_tab === OVERVIEW ? (
                        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                            <div>
                                {TabComponent && (
                                    <ModuleSlot
                                        fallback={<Skeleton className="h-48" />}
                                    >
                                        <TabComponent profile={profile} />
                                    </ModuleSlot>
                                )}
                            </div>
                            <div className="flex flex-col gap-4">
                                {overview_blocks.map((block) => (
                                    <ModuleBlock
                                        key={block.key}
                                        block={block}
                                        profile={profile}
                                        onShowAll={
                                            tabs.some(
                                                (tab) => tab.key === block.key,
                                            )
                                                ? () => selectTab(block.key)
                                                : undefined
                                        }
                                    />
                                ))}
                            </div>
                        </div>
                    ) : (
                        TabComponent && (
                            <ModuleSlot
                                fallback={<Skeleton className="h-48" />}
                            >
                                <TabComponent
                                    profile={profile}
                                    tab_data={tab_data ?? {}}
                                />
                            </ModuleSlot>
                        )
                    )}
                </div>
            </div>
        </>
    );
}
