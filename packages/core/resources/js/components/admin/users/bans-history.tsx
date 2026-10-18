import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@ladcms/core/components/confirm-dialog';
import { Badge } from '@ladcms/core/components/ui/badge';
import { Button } from '@ladcms/core/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@ladcms/core/components/ui/card';
import {
    useDateFormat,
    type BanItem,
    type UserDetail,
    type ViolationCode,
} from '@ladcms/core/pages/admin/users/show';
import { useT } from '@ladcms/core/lib/i18n';

export function BansHistory({
    user,
    bans,
    codes,
    canLift,
}: {
    user: UserDetail;
    bans: BanItem[];
    codes: ViolationCode[];
    canLift: boolean;
}) {
    const t = useT();
    const formatDate = useDateFormat();
    const [lifting, setLifting] = useState<number | null>(null);
    const label = (code: string) =>
        codes.find((c) => c.code === code)?.label ?? code;

    const lift = (ban: BanItem) => {
        router.delete(`/admin/users/${user.id}/ban/${ban.id}`, {
            preserveScroll: true,
            onStart: () => setLifting(ban.id),
            onFinish: () => setLifting(null),
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    {t('core::admin.users.bans_history.title')}
                </CardTitle>
                {bans.length === 0 && (
                    <CardDescription>
                        {t('core::admin.users.bans_history.empty')}
                    </CardDescription>
                )}
            </CardHeader>
            {bans.length > 0 && (
                <CardContent>
                    <ul className="divide-y">
                        {bans.map((ban) => (
                            <li
                                key={ban.id}
                                className="flex flex-col gap-2 py-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div className="space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">
                                            {label(ban.reason_code)}
                                        </span>
                                        <Badge
                                            variant={
                                                ban.is_active
                                                    ? 'destructive'
                                                    : 'secondary'
                                            }
                                        >
                                            {ban.is_active
                                                ? t(
                                                      'core::admin.users.bans_history.active',
                                                  )
                                                : t(
                                                      'core::admin.users.bans_history.expired',
                                                  )}
                                        </Badge>
                                        <Badge variant="outline">
                                            {t(
                                                `core::admin.users.bans_history.types.${ban.type}`,
                                            )}
                                        </Badge>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            'core::admin.users.bans_history.period',
                                        )}
                                        : {formatDate(ban.starts_at)} —{' '}
                                        {ban.expires_at === null
                                            ? t(
                                                  'core::admin.users.bans_history.forever',
                                              )
                                            : formatDate(ban.expires_at)}
                                        {ban.banned_by &&
                                            ` · ${t('core::admin.users.bans_history.by')}: ${ban.banned_by.login}`}
                                    </p>
                                    {ban.comment && (
                                        <p className="text-sm">{ban.comment}</p>
                                    )}
                                </div>
                                {canLift && ban.is_active && (
                                    <ConfirmDialog
                                        trigger={
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={lifting === ban.id}
                                            >
                                                {t(
                                                    'core::admin.users.bans_history.lift',
                                                )}
                                            </Button>
                                        }
                                        title={t(
                                            'core::admin.users.bans_history.lift_title',
                                        )}
                                        description={t(
                                            'core::admin.users.bans_history.lift_description',
                                        )}
                                        confirmLabel={t(
                                            'core::admin.users.bans_history.lift',
                                        )}
                                        onConfirm={() => lift(ban)}
                                    />
                                )}
                            </li>
                        ))}
                    </ul>
                </CardContent>
            )}
        </Card>
    );
}
