import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useMemo } from 'react';
import { Button } from '@ladcms/core/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@ladcms/core/components/ui/card';
import { Input } from '@ladcms/core/components/ui/input';
import { Label } from '@ladcms/core/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@ladcms/core/components/ui/select';
import { Textarea } from '@ladcms/core/components/ui/textarea';
import type { UserDetail } from '@ladcms/core/pages/admin/users/show';
import { useT } from '@ladcms/core/lib/i18n';

/** Значение Select для «не указан»: у Radix Select не может быть пустого значения. */
const NO_SEX = 'none';

type ProfileData = {
    name: string;
    email: string;
    sex: '' | 'male' | 'female';
    birth_date: string;
    about: string;
    signature: string;
    language: string;
    timezone: string;
};

function timezones(): string[] {
    try {
        return Intl.supportedValuesOf('timeZone');
    } catch {
        return [];
    }
}

export function ProfileForm({
    user,
    disabled,
}: {
    user: UserDetail;
    disabled: boolean;
}) {
    const t = useT();
    const { props } = usePage();
    const locales =
        typeof props.locales === 'object' && props.locales !== null
            ? (props.locales as Record<string, string>)
            : {};
    const zones = useMemo(timezones, []);

    const form = useForm<ProfileData>({
        name: user.name,
        email: user.email,
        sex: user.sex ?? '',
        birth_date: user.birth_date ?? '',
        about: user.about ?? '',
        signature: user.signature ?? '',
        language: user.language,
        timezone: user.timezone,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.patch(`/admin/users/${user.id}`, { preserveScroll: true });
    };

    const field = (id: keyof ProfileData, label: string, input: ReactNode) => (
        <div className="grid gap-2">
            <Label htmlFor={`profile-${id}`}>{label}</Label>
            {input}
            {form.errors[id] && (
                <p
                    id={`profile-${id}-error`}
                    className="text-sm text-destructive"
                >
                    {form.errors[id]}
                </p>
            )}
        </div>
    );

    const invalid = (id: keyof ProfileData) =>
        form.errors[id]
            ? {
                  'aria-invalid': true,
                  'aria-describedby': `profile-${id}-error`,
              }
            : {};

    return (
        <Card>
            <form onSubmit={submit}>
                <CardHeader>
                    <CardTitle>
                        {t('core::admin.users.profile.title')}
                    </CardTitle>
                    <CardDescription>
                        {disabled
                            ? t('core::admin.users.profile.readonly')
                            : t('core::admin.users.profile.description')}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <fieldset
                        disabled={disabled}
                        className="grid gap-4 md:grid-cols-2"
                    >
                        {field(
                            'name',
                            t('core::admin.users.profile.name'),
                            <Input
                                id="profile-name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                {...invalid('name')}
                            />,
                        )}
                        {field(
                            'email',
                            t('core::admin.users.profile.email'),
                            <Input
                                id="profile-email"
                                type="email"
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                                {...invalid('email')}
                            />,
                        )}
                        {field(
                            'sex',
                            t('core::admin.users.profile.sex'),
                            <Select
                                disabled={disabled}
                                value={
                                    form.data.sex === ''
                                        ? NO_SEX
                                        : form.data.sex
                                }
                                onValueChange={(value) =>
                                    form.setData(
                                        'sex',
                                        value === NO_SEX
                                            ? ''
                                            : (value as 'male' | 'female'),
                                    )
                                }
                            >
                                <SelectTrigger
                                    id="profile-sex"
                                    {...invalid('sex')}
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NO_SEX}>
                                        {t(
                                            'core::admin.users.profile.sexes.none',
                                        )}
                                    </SelectItem>
                                    <SelectItem value="male">
                                        {t(
                                            'core::admin.users.profile.sexes.male',
                                        )}
                                    </SelectItem>
                                    <SelectItem value="female">
                                        {t(
                                            'core::admin.users.profile.sexes.female',
                                        )}
                                    </SelectItem>
                                </SelectContent>
                            </Select>,
                        )}
                        {field(
                            'birth_date',
                            t('core::admin.users.profile.birth_date'),
                            <Input
                                id="profile-birth_date"
                                type="date"
                                value={form.data.birth_date}
                                onChange={(e) =>
                                    form.setData('birth_date', e.target.value)
                                }
                                {...invalid('birth_date')}
                            />,
                        )}
                        {field(
                            'language',
                            t('core::admin.users.profile.language'),
                            <Select
                                disabled={disabled}
                                value={form.data.language}
                                onValueChange={(value) =>
                                    form.setData('language', value)
                                }
                            >
                                <SelectTrigger
                                    id="profile-language"
                                    {...invalid('language')}
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(locales).map(
                                        ([code, label]) => (
                                            <SelectItem key={code} value={code}>
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>,
                        )}
                        {field(
                            'timezone',
                            t('core::admin.users.profile.timezone'),
                            <>
                                <Input
                                    id="profile-timezone"
                                    list="profile-timezones"
                                    value={form.data.timezone}
                                    onChange={(e) =>
                                        form.setData('timezone', e.target.value)
                                    }
                                    {...invalid('timezone')}
                                />
                                <datalist id="profile-timezones">
                                    {zones.map((zone) => (
                                        <option key={zone} value={zone} />
                                    ))}
                                </datalist>
                            </>,
                        )}
                        <div className="md:col-span-2">
                            {field(
                                'about',
                                t('core::admin.users.profile.about'),
                                <Textarea
                                    id="profile-about"
                                    rows={4}
                                    value={form.data.about}
                                    onChange={(e) =>
                                        form.setData('about', e.target.value)
                                    }
                                    {...invalid('about')}
                                />,
                            )}
                        </div>
                        <div className="md:col-span-2">
                            {field(
                                'signature',
                                t('core::admin.users.profile.signature'),
                                <Textarea
                                    id="profile-signature"
                                    rows={2}
                                    value={form.data.signature}
                                    onChange={(e) =>
                                        form.setData(
                                            'signature',
                                            e.target.value,
                                        )
                                    }
                                    {...invalid('signature')}
                                />,
                            )}
                        </div>
                    </fieldset>
                </CardContent>
                {!disabled && (
                    <CardFooter className="justify-end pt-6">
                        <Button type="submit" disabled={form.processing}>
                            {t('core::admin.users.profile.save')}
                        </Button>
                    </CardFooter>
                )}
            </form>
        </Card>
    );
}
