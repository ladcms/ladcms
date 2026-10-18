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
import { Checkbox } from '@ladcms/core/components/ui/checkbox';
import { Label } from '@ladcms/core/components/ui/label';
import type { RoleItem, UserDetail } from '@ladcms/core/pages/admin/users/show';

const t = {
    title: 'Роли',
    description: 'Отметьте роли пользователя. Набор ролей заменяется целиком.',
    readonly: 'У вас нет прав на изменение ролей этого пользователя.',
    unavailable:
        'Недоступно: роль не ниже вашего уровня или даёт права, которых у вас нет.',
    level: 'Уровень',
    noLevel: 'без уровня',
    save: 'Сохранить роли',
};

export function RolesForm({
    user,
    roles,
    assignable,
    disabled,
}: {
    user: UserDetail;
    roles: RoleItem[];
    assignable: string[];
    disabled: boolean;
}) {
    const form = useForm<{ roles: string[] }>({
        roles: user.roles.map((role) => role.name),
    });

    const toggle = (name: string, checked: boolean) => {
        form.setData(
            'roles',
            checked
                ? [...form.data.roles, name]
                : form.data.roles.filter((role) => role !== name),
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(`/admin/users/${user.id}/roles`, { preserveScroll: true });
    };

    const errors = Object.entries(form.errors).filter(([key]) =>
        key.startsWith('roles'),
    );

    return (
        <Card>
            <form onSubmit={submit}>
                <CardHeader>
                    <CardTitle>{t.title}</CardTitle>
                    <CardDescription>
                        {disabled ? t.readonly : t.description}
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    <ul className="grid gap-3 sm:grid-cols-2">
                        {roles.map((role) => {
                            const locked =
                                disabled || !assignable.includes(role.name);
                            const id = `role-${role.id}`;

                            return (
                                <li
                                    key={role.id}
                                    className="flex items-start gap-3 rounded-md border p-3"
                                >
                                    <Checkbox
                                        id={id}
                                        checked={form.data.roles.includes(
                                            role.name,
                                        )}
                                        disabled={locked}
                                        onCheckedChange={(checked) =>
                                            toggle(role.name, checked === true)
                                        }
                                        aria-describedby={
                                            locked && !disabled
                                                ? `${id}-hint`
                                                : undefined
                                        }
                                    />
                                    <div className="grid gap-1">
                                        <Label
                                            htmlFor={id}
                                            className="flex items-center gap-2"
                                        >
                                            {role.color && (
                                                <span
                                                    aria-hidden
                                                    className="size-2.5 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            role.color,
                                                    }}
                                                />
                                            )}
                                            {role.name}
                                        </Label>
                                        <span className="text-xs text-muted-foreground">
                                            {t.level}:{' '}
                                            {role.level ?? t.noLevel}
                                        </span>
                                        {locked && !disabled && (
                                            <span
                                                id={`${id}-hint`}
                                                className="text-xs text-muted-foreground"
                                            >
                                                {t.unavailable}
                                            </span>
                                        )}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                    {errors.map(([key, message]) => (
                        <p key={key} className="text-sm text-destructive">
                            {message}
                        </p>
                    ))}
                </CardContent>
                {!disabled && (
                    <CardFooter className="justify-end pt-6">
                        <Button type="submit" disabled={form.processing}>
                            {t.save}
                        </Button>
                    </CardFooter>
                )}
            </form>
        </Card>
    );
}
