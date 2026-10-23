import { OverviewCard } from '@ladcms/core/components/profile/overview-card';
import type { Profile } from '@ladcms/core/types/profile';

/** Вкладка «Обзор», левая колонка: карточка анкеты (блоки модулей рисует страница справа). */
export default function Overview({ profile }: { profile: Profile }) {
    return <OverviewCard profile={profile} />;
}
