import {
    Form,
    router,
    setLayoutProps,
    useForm,
    usePage,
} from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { useEffect } from 'react';
import RolesController from '@/actions/Ladcms/Core/Http/Controllers/Admin/RolesController';
import { ConfirmDialog } from '@ladcms/core/components/confirm-dialog';
import { PageHeader } from '@ladcms/core/components/page-header';
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@ladcms/core/components/ui/alert';
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
import { Input } from '@ladcms/core/components/ui/input';
import { Label } from '@ladcms/core/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@ladcms/core/components/ui/tooltip';
import type { AdminBreadcrumb } from '@ladcms/core/types/admin';
import { roleName, useT } from '@ladcms/core/lib/i18n';

/** `RoleResource`. */
type RoleDetail = {
    id: number;
    name: string;
    level: number | null;
    color: string | null;
    is_default: boolean;
    is_system: boolean;
    users_count?: number;
    permissions_count?: number;
};

type PermissionItem = { name: string; label: string };

type Props = {
    role: RoleDetail;
    /** Права по модулям (префикс до точки). */
    permissions: Record<string, PermissionItem[]>;
    granted: string[];
    grantable: string[];
    can: {
        update: boolean;
        rename: boolean;
        assignLevel: boolean;
        delete: boolean;
    };
};

type LayoutProps = { title: string; breadcrumbs: AdminBreadcrumb[] };

function FieldError({ message }: { message?: string }) {
    return message ? (
        <p className="text-sm text-destructive">{message}</p>
    ) : null;
}

function RoleForm({ role, can }: Pick<Props, 'role' | 'can'>) {
    const t = useT();
    return (
        <Card>
            <Form
                {...RolesController.update.form(role.id)}
                options={{ preserveScroll: true }}
            >
                {({ errors, processing }) => (
                    <>
                        <CardHeader>
                            <CardTitle>
                                {t('core::admin.roles.edit.form.title')}
                            </CardTitle>
                            <CardDescription>
                                {t('core::admin.roles.edit.form.description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:max-w-md">
                            <div className="grid gap-2">
                                <Label htmlFor="role-name">
                                    {t('core::admin.roles.edit.form.name')}
                                </Label>
                                <Input
                                    id="role-name"
                                    name="name"
                                    defaultValue={role.name}
                                    disabled={!can.rename}
                                    autoComplete="off"
                                />
                                <FieldError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="role-level">
                                    {t('core::admin.roles.edit.form.level')}
                                </Label>
                                <Input
                                    id="role-level"
                                    name="level"
                                    type="number"
                                    min={0}
                                    max={6}
                                    defaultValue={role.level ?? ''}
                                    placeholder={t(
                                        'core::admin.roles.edit.form.level_hint',
                                    )}
                                    disabled={!can.assignLevel}
                                />
                                <FieldError message={errors.level} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="role-color">
                                    {t('core::admin.roles.edit.form.color')}
                                </Label>
                                <Input
                                    id="role-color"
                                    name="color"
                                    placeholder="#rrggbb"
                                    pattern="#[0-9a-fA-F]{6}"
                                    autoComplete="off"
                                    defaultValue={role.color ?? ''}
                                    disabled={!can.update}
                                />
                                <FieldError message={errors.color} />
                            </div>
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="role-default"
                                    name="is_default"
                                    value="1"
                                    defaultChecked={role.is_default}
                                    disabled={!can.update || role.is_default}
                                />
                                <Label htmlFor="role-default">
                                    {t(
                                        'core::admin.roles.edit.form.is_default',
                                    )}
                                </Label>
                            </div>
                            <FieldError message={errors.is_default} />
                        </CardContent>
                        {can.update && (
                            <CardFooter className="justify-end pt-6">
                                <Button type="submit" disabled={processing}>
                                    {t('core::admin.roles.edit.form.save')}
                                </Button>
                            </CardFooter>
                        )}
                    </>
                )}
            </Form>
        </Card>
    );
}

function PermissionsMatrix({
    role,
    permissions,
    granted,
    grantable,
    can,
}: Props) {
    const t = useT();
    const form = useForm<{ permissions: string[] }>({ permissions: granted });

    const toggle = (name: string, checked: boolean) => {
        form.setData(
            'permissions',
            checked
                ? [...form.data.permissions, name]
                : form.data.permissions.filter((item) => item !== name),
        );
    };

    const errors = Object.entries(form.errors).filter(([key]) =>
        key.startsWith('permissions'),
    );

    return (
        <Card>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(RolesController.permissions.url(role.id), {
                        preserveScroll: true,
                    });
                }}
            >
                <CardHeader>
                    <CardTitle>
                        {t('core::admin.roles.edit.matrix.title')}
                    </CardTitle>
                    <CardDescription>
                        {can.update
                            ? t('core::admin.roles.edit.matrix.description')
                            : t('core::admin.roles.edit.matrix.readonly')}
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <TooltipProvider>
                        {Object.entries(permissions).map(([module, items]) => (
                            <section key={module} className="space-y-3">
                                <h3 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                    {module}
                                </h3>
                                <ul className="grid gap-3 sm:grid-cols-2">
                                    {items.map((item) => {
                                        const locked = !grantable.includes(
                                            item.name,
                                        );
                                        const id = `permission-${item.name}`;
                                        const checkbox = (
                                            <Checkbox
                                                id={id}
                                                checked={form.data.permissions.includes(
                                                    item.name,
                                                )}
                                                disabled={locked}
                                                onCheckedChange={(checked) =>
                                                    toggle(
                                                        item.name,
                                                        checked === true,
                                                    )
                                                }
                                            />
                                        );

                                        return (
                                            <li
                                                key={item.name}
                                                className="flex items-start gap-3 rounded-md border p-3"
                                            >
                                                {locked && can.update ? (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <span>
                                                                {checkbox}
                                                            </span>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            {t(
                                                                'core::admin.roles.edit.matrix.not_grantable',
                                                            )}
                                                        </TooltipContent>
                                                    </Tooltip>
                                                ) : (
                                                    checkbox
                                                )}
                                                <div className="grid gap-1">
                                                    <Label htmlFor={id}>
                                                        {item.label}
                                                    </Label>
                                                    <code className="text-xs text-muted-foreground">
                                                        {item.name}
                                                    </code>
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </section>
                        ))}
                    </TooltipProvider>
                    {errors.map(([key, message]) => (
                        <p key={key} className="text-sm text-destructive">
                            {message}
                        </p>
                    ))}
                </CardContent>
                {can.update && (
                    <CardFooter className="justify-end pt-6">
                        <Button type="submit" disabled={form.processing}>
                            {t('core::admin.roles.edit.matrix.save')}
                        </Button>
                    </CardFooter>
                )}
            </form>
        </Card>
    );
}

export default function RoleEdit(props: Props) {
    const t = useT();
    const { role, can } = props;
    const { props: page } = usePage<{ errors: Record<string, string> }>();
    const general = page.errors.general;

    useEffect(() => {
        setLayoutProps<LayoutProps>({
            title: `${t('core::admin.roles.edit.title')} ${roleName(t, role.name)}`,
            breadcrumbs: [
                {
                    title: t('core::admin.roles.edit.roles'),
                    href: RolesController.index.url(),
                },
                { title: roleName(t, role.name) },
            ],
        });
    }, [role.name, t]);

    return (
        <div className="space-y-6">
            <PageHeader
                title={roleName(t, role.name)}
                description={`${t('core::admin.roles.edit.form.level')}: ${role.level ?? '—'}`}
            />

            {general && (
                <Alert variant="destructive">
                    <AlertCircle aria-hidden />
                    <AlertTitle>{t('core::admin.roles.edit.error')}</AlertTitle>
                    <AlertDescription>{general}</AlertDescription>
                </Alert>
            )}

            <RoleForm role={role} can={can} />
            <PermissionsMatrix {...props} />

            {can.delete && (
                <Card className="border-destructive/50">
                    <CardHeader>
                        <CardTitle className="text-destructive">
                            {t('core::admin.roles.edit.delete.title')}
                        </CardTitle>
                        <CardDescription>
                            {t('core::admin.roles.edit.delete.description')}
                        </CardDescription>
                    </CardHeader>
                    <CardFooter className="justify-end">
                        <ConfirmDialog
                            trigger={
                                <Button variant="destructive">
                                    {t('core::admin.roles.edit.delete.submit')}
                                </Button>
                            }
                            title={t(
                                'core::admin.roles.edit.delete.confirm_title',
                            )}
                            description={t(
                                'core::admin.roles.edit.delete.confirm_description',
                                { name: role.name },
                            )}
                            confirmLabel={t(
                                'core::admin.roles.edit.delete.submit',
                            )}
                            destructive
                            onConfirm={() =>
                                router.delete(
                                    RolesController.destroy.url(role.id),
                                )
                            }
                        />
                    </CardFooter>
                </Card>
            )}
        </div>
    );
}

RoleEdit.layout = {
    title: 'core::admin.roles.edit.title',
    breadcrumbs: [
        { title: 'core::admin.roles.edit.roles', href: '/admin/roles' },
        { title: 'core::admin.roles.edit.title' },
    ],
};
