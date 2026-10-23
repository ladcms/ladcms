import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { AvatarUploader } from '@ladcms/core/components/settings/avatar-uploader';
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
import { Seo } from '@ladcms/core/components/public/seo';
import { useT } from '@ladcms/core/lib/i18n';
import type { CurrentUser } from '@ladcms/core/types/admin';
import ProfileSettingsController from '@/actions/Ladcms/Core/Http/Controllers/Settings/ProfileSettingsController';
import { send } from '@/routes/verification';

/** Значение Select для «не указан»: у Radix Select не может быть пустого значения. */
const NO_SEX = 'none';

type Sex = '' | 'male' | 'female';

type ProfileProps = {
    login: string;
    name: string;
    email: string;
    email_verified: boolean;
    sex: Sex | null;
    birth_date: string | null;
    about: string | null;
    signature: string | null;
    timezone: string;
};

type Props = {
    profile: ProfileProps;
    avatar_url: string | null;
    avatar_max_kb: number;
    avatar_max_dimension: number;
    must_verify_email: boolean;
    status?: string | null;
    timezones: string[];
};

type ProfileData = {
    name: string;
    email: string;
    sex: Sex;
    birth_date: string;
    about: string;
    signature: string;
    timezone: string;
};

export default function ProfileSettings({
    profile,
    avatar_url: avatarUrl,
    avatar_max_kb: avatarMaxKb,
    avatar_max_dimension: avatarMaxDimension,
    must_verify_email: mustVerifyEmail,
    status,
    timezones,
}: Props) {
    const t = useT();
    const { auth } = usePage<{ auth: { user: CurrentUser } }>().props;
    const readonly = !auth.user.can_write;

    const form = useForm<ProfileData>({
        name: profile.name,
        email: profile.email,
        sex: profile.sex ?? '',
        birth_date: profile.birth_date ?? '',
        about: profile.about ?? '',
        signature: profile.signature ?? '',
        timezone: profile.timezone,
    });
    const loginForm = useForm({ login: profile.login });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.patch(ProfileSettingsController.update.url(), {
            preserveScroll: true,
        });
    };

    const submitLogin = (event: FormEvent) => {
        event.preventDefault();
        loginForm.patch(ProfileSettingsController.updateLogin.url(), {
            preserveScroll: true,
        });
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
        <>
            <Seo title={t('core::public.settings.profile.title')} />

            <Card>
                <CardHeader>
                    <CardTitle>
                        {t('core::public.settings.profile.avatar_title')}
                    </CardTitle>
                    <CardDescription>
                        {t('core::public.settings.profile.avatar_description')}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <AvatarUploader
                        name={profile.name}
                        avatarUrl={avatarUrl}
                        maxKb={avatarMaxKb}
                        maxDimension={avatarMaxDimension}
                    />
                </CardContent>
            </Card>

            <Card>
                <form onSubmit={submit}>
                    <CardHeader>
                        <CardTitle>
                            {t('core::public.settings.profile.title')}
                        </CardTitle>
                        <CardDescription>
                            {readonly
                                ? t(
                                      'core::public.settings.profile.readonly_note',
                                  )
                                : t(
                                      'core::public.settings.profile.description',
                                  )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        {field(
                            'name',
                            t('core::public.settings.profile.name'),
                            <Input
                                id="profile-name"
                                value={form.data.name}
                                disabled={readonly}
                                autoComplete="name"
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                {...invalid('name')}
                            />,
                        )}
                        <div className="grid gap-2">
                            {field(
                                'email',
                                t('core::public.settings.profile.email'),
                                <Input
                                    id="profile-email"
                                    type="email"
                                    value={form.data.email}
                                    autoComplete="email"
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                    {...invalid('email')}
                                />,
                            )}
                            {mustVerifyEmail && !profile.email_verified && (
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'core::public.settings.profile.email_unverified',
                                    )}{' '}
                                    <Link
                                        href={send()}
                                        as="button"
                                        className="text-foreground underline underline-offset-4"
                                    >
                                        {t(
                                            'core::public.settings.profile.resend',
                                        )}
                                    </Link>
                                </p>
                            )}
                            {status === 'verification-link-sent' && (
                                <p
                                    role="status"
                                    className="text-sm font-medium text-green-600"
                                >
                                    {t(
                                        'core::public.settings.profile.link_sent',
                                    )}
                                </p>
                            )}
                        </div>
                        {field(
                            'sex',
                            t('core::public.settings.profile.sex'),
                            <Select
                                value={
                                    form.data.sex === ''
                                        ? NO_SEX
                                        : form.data.sex
                                }
                                onValueChange={(value) =>
                                    form.setData(
                                        'sex',
                                        value === NO_SEX ? '' : (value as Sex),
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
                                            'core::public.settings.profile.sexes.none',
                                        )}
                                    </SelectItem>
                                    <SelectItem value="male">
                                        {t(
                                            'core::public.settings.profile.sexes.male',
                                        )}
                                    </SelectItem>
                                    <SelectItem value="female">
                                        {t(
                                            'core::public.settings.profile.sexes.female',
                                        )}
                                    </SelectItem>
                                </SelectContent>
                            </Select>,
                        )}
                        {field(
                            'birth_date',
                            t('core::public.settings.profile.birth_date'),
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
                            'timezone',
                            t('core::public.settings.profile.timezone'),
                            <Select
                                value={form.data.timezone}
                                onValueChange={(value) =>
                                    form.setData('timezone', value)
                                }
                            >
                                <SelectTrigger
                                    id="profile-timezone"
                                    {...invalid('timezone')}
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {timezones.map((zone) => (
                                        <SelectItem key={zone} value={zone}>
                                            {zone}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>,
                        )}
                        <div className="md:col-span-2">
                            {field(
                                'about',
                                t('core::public.settings.profile.about'),
                                <Textarea
                                    id="profile-about"
                                    rows={4}
                                    value={form.data.about}
                                    disabled={readonly}
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
                                t('core::public.settings.profile.signature'),
                                <Textarea
                                    id="profile-signature"
                                    rows={2}
                                    value={form.data.signature}
                                    disabled={readonly}
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
                    </CardContent>
                    <CardFooter className="justify-end pt-6">
                        <Button type="submit" disabled={form.processing}>
                            {t('core::public.settings.save')}
                        </Button>
                    </CardFooter>
                </form>
            </Card>

            <Card>
                <form onSubmit={submitLogin}>
                    <CardHeader>
                        <CardTitle>
                            {t('core::public.settings.profile.login_title')}
                        </CardTitle>
                        <CardDescription>
                            {t(
                                'core::public.settings.profile.login_description',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        {readonly && (
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'core::public.settings.profile.readonly_note',
                                )}
                            </p>
                        )}
                        <Label htmlFor="profile-login">
                            {t('core::public.settings.profile.login')}
                        </Label>
                        <Input
                            id="profile-login"
                            value={loginForm.data.login}
                            disabled={readonly}
                            autoComplete="username"
                            onChange={(e) =>
                                loginForm.setData('login', e.target.value)
                            }
                            aria-invalid={
                                loginForm.errors.login ? true : undefined
                            }
                            aria-describedby={
                                loginForm.errors.login
                                    ? 'profile-login-error'
                                    : undefined
                            }
                        />
                        {loginForm.errors.login && (
                            <p
                                id="profile-login-error"
                                className="text-sm text-destructive"
                            >
                                {loginForm.errors.login}
                            </p>
                        )}
                    </CardContent>
                    <CardFooter className="justify-end pt-6">
                        <Button
                            type="submit"
                            disabled={loginForm.processing || readonly}
                        >
                            {t('core::public.settings.profile.login_save')}
                        </Button>
                    </CardFooter>
                </form>
            </Card>
        </>
    );
}
