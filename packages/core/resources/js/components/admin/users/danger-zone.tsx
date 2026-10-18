import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { ConfirmDialog } from '@ladcms/core/components/confirm-dialog';
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
import type {
    UserAbilities,
    UserDetail,
} from '@ladcms/core/pages/admin/users/show';

const t = {
    login: {
        title: 'Смена логина',
        description:
            'Старый логин сохранится в истории и некоторое время будет недоступен для регистрации.',
        label: 'Новый логин',
        submit: 'Сменить логин',
        confirmTitle: 'Сменить логин?',
        confirmDescription: (from: string, to: string) =>
            `Логин «${from}» будет заменён на «${to}».`,
    },
    delete: {
        title: 'Удаление пользователя',
        description:
            'Аккаунт будет удалён (мягкое удаление): пользователь не сможет войти.',
        submit: 'Удалить пользователя',
        confirmTitle: 'Удалить пользователя?',
        confirmDescription: (login: string) =>
            `Пользователь «${login}» будет удалён.`,
    },
};

export function DangerZone({
    user,
    can,
}: {
    user: UserDetail;
    can: UserAbilities;
}) {
    const loginForm = useForm<{ login: string }>({ login: user.login });
    const deleteForm = useForm<Record<string, never>>({});

    const changeLogin = () => {
        loginForm.patch(`/admin/users/${user.id}/login`, {
            preserveScroll: true,
        });
    };

    const submitLogin = (event: FormEvent) => {
        event.preventDefault();
    };

    const unchanged =
        loginForm.data.login.trim().toLowerCase() === user.login ||
        loginForm.data.login.trim() === '';

    return (
        <div className="space-y-6">
            {can.update && (
                <Card>
                    <form onSubmit={submitLogin}>
                        <CardHeader>
                            <CardTitle>{t.login.title}</CardTitle>
                            <CardDescription>
                                {t.login.description}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-2 sm:max-w-sm">
                            <Label htmlFor="danger-login">
                                {t.login.label}
                            </Label>
                            <Input
                                id="danger-login"
                                autoComplete="off"
                                value={loginForm.data.login}
                                onChange={(e) =>
                                    loginForm.setData('login', e.target.value)
                                }
                                aria-invalid={
                                    loginForm.errors.login ? true : undefined
                                }
                                aria-describedby={
                                    loginForm.errors.login
                                        ? 'danger-login-error'
                                        : undefined
                                }
                            />
                            {loginForm.errors.login && (
                                <p
                                    id="danger-login-error"
                                    className="text-sm text-destructive"
                                >
                                    {loginForm.errors.login}
                                </p>
                            )}
                        </CardContent>
                        <CardFooter className="justify-end pt-6">
                            <ConfirmDialog
                                trigger={
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={
                                            unchanged || loginForm.processing
                                        }
                                    >
                                        {t.login.submit}
                                    </Button>
                                }
                                title={t.login.confirmTitle}
                                description={t.login.confirmDescription(
                                    user.login,
                                    loginForm.data.login.trim().toLowerCase(),
                                )}
                                confirmLabel={t.login.submit}
                                onConfirm={changeLogin}
                            />
                        </CardFooter>
                    </form>
                </Card>
            )}

            {can.delete && (
                <Card className="border-destructive/50">
                    <CardHeader>
                        <CardTitle className="text-destructive">
                            {t.delete.title}
                        </CardTitle>
                        <CardDescription>{t.delete.description}</CardDescription>
                    </CardHeader>
                    <CardFooter className="justify-end">
                        <ConfirmDialog
                            trigger={
                                <Button
                                    variant="destructive"
                                    disabled={deleteForm.processing}
                                >
                                    {t.delete.submit}
                                </Button>
                            }
                            title={t.delete.confirmTitle}
                            description={t.delete.confirmDescription(
                                user.login,
                            )}
                            confirmLabel={t.delete.submit}
                            destructive
                            onConfirm={() =>
                                deleteForm.delete(`/admin/users/${user.id}`)
                            }
                        />
                    </CardFooter>
                </Card>
            )}
        </div>
    );
}
