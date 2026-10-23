import { Link } from '@inertiajs/react';
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
import { useT } from '@ladcms/core/lib/i18n';
import { initials } from '@ladcms/core/lib/initials';
import { show as userProfile } from '@/routes/users';

type OnlineUser = {
    id: number;
    login: string;
    display_name: string;
    avatar_url: string | null;
};

type Props = { count: number; users: OnlineUser[] };

export default function Online({ count, users }: Props) {
    const t = useT();

    return (
        <section>
            <CardHeader className="p-4 pb-2">
                <CardTitle className="flex items-center gap-2 text-base">
                    <span
                        aria-hidden
                        className="size-2 rounded-full bg-emerald-500"
                    />
                    {t('core::public.widgets.online.title')}
                    <span className="ml-auto text-sm font-normal text-muted-foreground">
                        {count}
                    </span>
                </CardTitle>
            </CardHeader>
            <CardContent className="p-4 pt-0">
                {users.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('core::public.widgets.online.empty')}
                    </p>
                ) : (
                    <ul className="flex flex-wrap gap-2">
                        {users.map((user) => (
                            <li key={user.id}>
                                <Link
                                    href={userProfile(user.login)}
                                    title={user.display_name}
                                    aria-label={user.display_name}
                                    className="relative block rounded-full"
                                >
                                    <Avatar className="size-9">
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
                                    <span
                                        aria-hidden
                                        className="absolute right-0 bottom-0 size-2.5 rounded-full border-2 border-card bg-emerald-500"
                                    />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </section>
    );
}
