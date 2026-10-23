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
import { Label } from '@ladcms/core/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@ladcms/core/components/ui/select';
import { Switch } from '@ladcms/core/components/ui/switch';
import { Seo } from '@ladcms/core/components/public/seo';
import { useT } from '@ladcms/core/lib/i18n';
import PrivacyController from '@/actions/Ladcms/Core/Http/Controllers/Settings/PrivacyController';

type Privacy = {
    show_email: boolean;
    show_birthday: boolean;
    messages_from: string;
};

type Props = {
    privacy: Privacy;
    messages_from_options: string[];
};

export default function PrivacySettings({
    privacy,
    messages_from_options: options,
}: Props) {
    const t = useT();
    const form = useForm<Privacy>({ ...privacy });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.patch(PrivacyController.update.url(), { preserveScroll: true });
    };

    return (
        <>
            <Seo title={t('core::public.settings.privacy.title')} />

            <Card>
                <form onSubmit={submit}>
                    <CardHeader>
                        <CardTitle>
                            {t('core::public.settings.privacy.title')}
                        </CardTitle>
                        <CardDescription>
                            {t('core::public.settings.privacy.description')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="flex items-start justify-between gap-4">
                            <div className="space-y-1">
                                <Label htmlFor="privacy-show_email">
                                    {t(
                                        'core::public.settings.privacy.show_email',
                                    )}
                                </Label>
                                <p
                                    id="privacy-show_email-hint"
                                    className="text-sm text-muted-foreground"
                                >
                                    {t(
                                        'core::public.settings.privacy.show_email_hint',
                                    )}
                                </p>
                            </div>
                            <Switch
                                id="privacy-show_email"
                                aria-describedby="privacy-show_email-hint"
                                checked={form.data.show_email}
                                onCheckedChange={(value) =>
                                    form.setData('show_email', value)
                                }
                            />
                        </div>
                        <div className="flex items-start justify-between gap-4">
                            <div className="space-y-1">
                                <Label htmlFor="privacy-show_birthday">
                                    {t(
                                        'core::public.settings.privacy.show_birthday',
                                    )}
                                </Label>
                                <p
                                    id="privacy-show_birthday-hint"
                                    className="text-sm text-muted-foreground"
                                >
                                    {t(
                                        'core::public.settings.privacy.show_birthday_hint',
                                    )}
                                </p>
                            </div>
                            <Switch
                                id="privacy-show_birthday"
                                aria-describedby="privacy-show_birthday-hint"
                                checked={form.data.show_birthday}
                                onCheckedChange={(value) =>
                                    form.setData('show_birthday', value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="privacy-messages_from">
                                {t(
                                    'core::public.settings.privacy.messages_from',
                                )}
                            </Label>
                            <Select
                                value={form.data.messages_from}
                                onValueChange={(value) =>
                                    form.setData('messages_from', value)
                                }
                            >
                                <SelectTrigger id="privacy-messages_from">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.map((option) => (
                                        <SelectItem key={option} value={option}>
                                            {t(
                                                `core::public.settings.privacy.options.${option}`,
                                            )}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.messages_from && (
                                <p className="text-sm text-destructive">
                                    {form.errors.messages_from}
                                </p>
                            )}
                        </div>
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
