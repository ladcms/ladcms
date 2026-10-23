import { Link, usePage } from '@inertiajs/react';
import {
    Avatar,
    AvatarFallback,
    AvatarImage,
} from '@ladcms/core/components/ui/avatar';
import {
    CardContent,
    CardHeader,
    CardTitle,
} from '@ladcms/core/components/ui/card';
import { initials } from '@ladcms/core/lib/initials';
import { useT } from '@ladcms/core/lib/i18n';
import { relativeTime } from '@ladcms/core/lib/relative-time';
import { show as userProfile } from '@/routes/users';

type LatestUser = {
    id: number;
    login: string;
    display_name: string;
    avatar_url: string | null;
    created_at: string;
};

type Props = { users: LatestUser[] };

export default function LatestUsers({ users }: Props) {
    const t = useT();
    const { locale } = usePage<{ locale?: string }>().props;

    return (
        <section>
            <CardHeader className="p-4 pb-2">
                <CardTitle className="text-base">
                    {t('core::public.widgets.latest_users.title')}
                </CardTitle>
            </CardHeader>
            <CardContent className="p-4 pt-0">
                {users.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('core::public.widgets.latest_users.empty')}
                    </p>
                ) : (
                    <ul className="flex flex-col gap-2">
                        {users.map((user) => (
                            <li key={user.id}>
                                <Link
                                    href={userProfile(user.login)}
                                    className="flex items-center gap-3 rounded-lg p-1 hover:bg-accent"
                                >
                                    <Avatar className="size-8">
                                        {user.avatar_url && (
                                            <AvatarImage
                                                src={user.avatar_url}
                                                alt=""
                                            />
                                        )}
                                        <AvatarFallback>
                                            {initials(user.display_name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-medium">
                                            {user.display_name}
                                        </span>
                                        <time
                                            dateTime={user.created_at}
                                            className="block text-xs text-muted-foreground"
                                        >
                                            {relativeTime(
                                                user.created_at,
                                                locale ?? 'en',
                                            )}
                                        </time>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </section>
    );
}
