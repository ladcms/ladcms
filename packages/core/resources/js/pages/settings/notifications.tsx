import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@ladcms/core/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@ladcms/core/components/ui/card';
import { Switch } from '@ladcms/core/components/ui/switch';
import { Seo } from '@ladcms/core/components/public/seo';
import { useT } from '@ladcms/core/lib/i18n';
import NotificationPreferencesController from '@/actions/Ladcms/Core/Http/Controllers/Settings/NotificationPreferencesController';

type Channel = 'database' | 'mail';
type Preferences = Record<string, Record<Channel, boolean>>;

type Props = {
    types: string[];
    preferences: Preferences;
    /** Заблокированные ячейки матрицы: `<type>.<channel>`. */
    locked: string[];
};

const CHANNELS: Channel[] = ['database', 'mail'];

export default function NotificationSettings({
    types,
    preferences,
    locked,
}: Props) {
    const t = useT();
    const form = useForm<{ preferences: Preferences }>({ preferences });

    const toggle = (type: string, channel: Channel, value: boolean) =>
        form.setData('preferences', {
            ...form.data.preferences,
            [type]: { ...form.data.preferences[type], [channel]: value },
        });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.patch(NotificationPreferencesController.update.url(), {
            preserveScroll: true,
        });
    };

    const typeLabel = (type: string): string => {
        const key = `core::public.settings.notifications.types.${type}`;
        const label = t(key);

        return label === key ? type : label;
    };

    return (
        <>
            <Seo title={t('core::public.settings.notifications.title')} />

            <Card>
                <form onSubmit={submit}>
                    <CardHeader>
                        <CardTitle>
                            {t('core::public.settings.notifications.title')}
                        </CardTitle>
                        <CardDescription>
                            {t(
                                'core::public.settings.notifications.description',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-muted-foreground">
                                    <th className="py-2 pr-4 font-medium">
                                        {t(
                                            'core::public.settings.notifications.type',
                                        )}
                                    </th>
                                    {CHANNELS.map((channel) => (
                                        <th
                                            key={channel}
                                            className="px-4 py-2 text-center font-medium"
                                        >
                                            {t(
                                                `core::public.settings.notifications.${channel}`,
                                            )}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {types.map((type) => (
                                    <tr key={type} className="border-t">
                                        <th
                                            scope="row"
                                            className="py-3 pr-4 text-left font-medium"
                                        >
                                            {typeLabel(type)}
                                        </th>
                                        {CHANNELS.map((channel) => {
                                            const isLocked = locked.includes(
                                                `${type}.${channel}`,
                                            );
                                            const label = `${typeLabel(type)}: ${t(`core::public.settings.notifications.${channel}`)}`;

                                            return (
                                                <td
                                                    key={channel}
                                                    className="px-4 py-3 text-center"
                                                >
                                                    <Switch
                                                        aria-label={
                                                            isLocked
                                                                ? `${label} (${t('core::public.settings.notifications.locked')})`
                                                                : label
                                                        }
                                                        checked={
                                                            isLocked ||
                                                            form.data
                                                                .preferences[
                                                                type
                                                            ][channel]
                                                        }
                                                        disabled={isLocked}
                                                        onCheckedChange={(
                                                            value,
                                                        ) =>
                                                            toggle(
                                                                type,
                                                                channel,
                                                                value,
                                                            )
                                                        }
                                                    />
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {form.errors.preferences && (
                            <p className="mt-2 text-sm text-destructive">
                                {form.errors.preferences}
                            </p>
                        )}
                    </CardContent>
                    <CardFooter className="justify-end pt-6">
                        <Button type="submit" disabled={form.processing}>
                            {t('core::public.settings.save')}
                        </Button>
                    </CardFooter>
                </form>
            </Card>
        </>
    );
}
