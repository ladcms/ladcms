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
import { Input } from '@ladcms/core/components/ui/input';
import { Label } from '@ladcms/core/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@ladcms/core/components/ui/select';
import { Switch } from '@ladcms/core/components/ui/switch';
import { Textarea } from '@ladcms/core/components/ui/textarea';
import type {
    UserDetail,
    ViolationCode,
} from '@ladcms/core/pages/admin/users/show';
import { useT } from '@ladcms/core/lib/i18n';

type BanData = {
    type: 'full' | 'readonly';
    reason_code: string;
    expires_in_days: string;
    permanent: boolean;
    comment: string;
};

function FieldError({ id, message }: { id: string; message?: string }) {
    return message ? (
        <p id={id} className="text-sm text-destructive">
            {message}
        </p>
    ) : null;
}

export function BanForm({
    user,
    codes,
}: {
    user: UserDetail;
    codes: ViolationCode[];
}) {
    const t = useT();
    const first = codes[0];
    const form = useForm<BanData>({
        type: 'full',
        reason_code: first?.code ?? '',
        expires_in_days: String(first?.min_days ?? 1),
        permanent: false,
        comment: '',
    });
    const code = codes.find((c) => c.code === form.data.reason_code);
    const permanentAllowed = code?.max_days === null;
    const errors = form.errors as Partial<Record<string, string>>;

    const selectCode = (value: string) => {
        const next = codes.find((c) => c.code === value);
        form.setData((data) => ({
            ...data,
            reason_code: value,
            expires_in_days: String(next?.min_days ?? 1),
            permanent: next?.max_days === null ? data.permanent : false,
        }));
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            expires_in_days: data.permanent
                ? null
                : Number(data.expires_in_days),
        }));
        form.post(`/admin/users/${user.id}/ban`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    if (codes.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>{t('core::admin.users.ban.title')}</CardTitle>
                    <CardDescription>
                        {t('core::admin.users.ban.no_codes')}
                    </CardDescription>
                </CardHeader>
            </Card>
        );
    }

    const durationError = errors.expires_in_days ?? errors.expires_at;

    return (
        <Card>
            <form onSubmit={submit}>
                <CardHeader>
                    <CardTitle>{t('core::admin.users.ban.title')}</CardTitle>
                    <CardDescription>
                        {t('core::admin.users.ban.description')}
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="ban-type">
                            {t('core::admin.users.ban.type')}
                        </Label>
                        <Select
                            value={form.data.type}
                            onValueChange={(value) =>
                                form.setData('type', value as BanData['type'])
                            }
                        >
                            <SelectTrigger
                                id="ban-type"
                                aria-invalid={errors.type ? true : undefined}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="full">
                                    {t('core::admin.users.ban.types.full')}
                                </SelectItem>
                                <SelectItem value="readonly">
                                    {t('core::admin.users.ban.types.readonly')}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <FieldError id="ban-type-error" message={errors.type} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="ban-reason">
                            {t('core::admin.users.ban.reason')}
                        </Label>
                        <Select
                            value={form.data.reason_code}
                            onValueChange={selectCode}
                        >
                            <SelectTrigger
                                id="ban-reason"
                                aria-invalid={
                                    errors.reason_code ? true : undefined
                                }
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {codes.map((c) => (
                                    <SelectItem key={c.code} value={c.code}>
                                        {c.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <FieldError
                            id="ban-reason-error"
                            message={errors.reason_code}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="ban-days">
                            {t('core::admin.users.ban.days')}
                            {code && (
                                <span className="font-normal text-muted-foreground">
                                    (
                                    {code.max_days === null
                                        ? t(
                                              'core::admin.users.ban.range_from',
                                              { min: code.min_days },
                                          )
                                        : t(
                                              'core::admin.users.ban.range_between',
                                              {
                                                  min: code.min_days,
                                                  max: code.max_days,
                                              },
                                          )}
                                    )
                                </span>
                            )}
                        </Label>
                        <Input
                            id="ban-days"
                            type="number"
                            inputMode="numeric"
                            min={code?.min_days ?? 1}
                            max={code?.max_days ?? undefined}
                            value={form.data.expires_in_days}
                            disabled={form.data.permanent}
                            onChange={(e) =>
                                form.setData('expires_in_days', e.target.value)
                            }
                            aria-invalid={durationError ? true : undefined}
                            aria-describedby={
                                durationError ? 'ban-days-error' : undefined
                            }
                        />
                        <FieldError
                            id="ban-days-error"
                            message={durationError}
                        />
                    </div>
                    {permanentAllowed && (
                        <div className="flex items-center gap-3 self-end pb-2">
                            <Switch
                                id="ban-permanent"
                                checked={form.data.permanent}
                                onCheckedChange={(checked) =>
                                    form.setData('permanent', checked)
                                }
                            />
                            <Label htmlFor="ban-permanent">
                                {t('core::admin.users.ban.permanent')}
                            </Label>
                        </div>
                    )}
                    <div className="grid gap-2 md:col-span-2">
                        <Label htmlFor="ban-comment">
                            {t('core::admin.users.ban.comment')}
                        </Label>
                        <Textarea
                            id="ban-comment"
                            rows={3}
                            maxLength={1000}
                            value={form.data.comment}
                            onChange={(e) =>
                                form.setData('comment', e.target.value)
                            }
                            aria-invalid={errors.comment ? true : undefined}
                        />
                        <FieldError
                            id="ban-comment-error"
                            message={errors.comment}
                        />
                    </div>
                </CardContent>
                <CardFooter className="justify-end pt-6">
                    <Button
                        type="submit"
                        variant="destructive"
                        disabled={form.processing}
                    >
                        {t('core::admin.users.ban.submit')}
                    </Button>
                </CardFooter>
            </form>
        </Card>
    );
}

export function SuspiciousForm({
    user,
    disabled,
}: {
    user: UserDetail;
    disabled: boolean;
}) {
    const t = useT();
    const form = useForm<{ is_suspicious: boolean; note: string }>({
        is_suspicious: user.is_suspicious,
        note: user.suspicious_note ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.patch(`/admin/users/${user.id}/suspicious`, {
            preserveScroll: true,
        });
    };

    return (
        <Card>
            <form onSubmit={submit}>
                <CardHeader>
                    <CardTitle>
                        {t('core::admin.users.ban.suspicious.title')}
                    </CardTitle>
                    <CardDescription>
                        {disabled
                            ? t('core::admin.users.ban.suspicious.readonly')
                            : t('core::admin.users.ban.suspicious.description')}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <fieldset disabled={disabled} className="grid gap-4">
                        <div className="flex items-center gap-3">
                            <Switch
                                id="suspicious-flag"
                                checked={form.data.is_suspicious}
                                disabled={disabled}
                                onCheckedChange={(checked) =>
                                    form.setData('is_suspicious', checked)
                                }
                            />
                            <Label htmlFor="suspicious-flag">
                                {t('core::admin.users.ban.suspicious.flag')}
                            </Label>
                        </div>
                        {form.data.is_suspicious && (
                            <div className="grid gap-2">
                                <Label htmlFor="suspicious-note">
                                    {t('core::admin.users.ban.suspicious.note')}
                                </Label>
                                <Input
                                    id="suspicious-note"
                                    maxLength={255}
                                    value={form.data.note}
                                    onChange={(e) =>
                                        form.setData('note', e.target.value)
                                    }
                                    aria-invalid={
                                        form.errors.note ? true : undefined
                                    }
                                />
                                <FieldError
                                    id="suspicious-note-error"
                                    message={form.errors.note}
                                />
                            </div>
                        )}
                    </fieldset>
                </CardContent>
                {!disabled && (
                    <CardFooter className="justify-end pt-6">
                        <Button type="submit" disabled={form.processing}>
                            {t('core::admin.users.ban.suspicious.save')}
                        </Button>
                    </CardFooter>
                )}
            </form>
        </Card>
    );
}
