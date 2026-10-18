import { Form, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import RolesController from '@/actions/Ladcms/Core/Http/Controllers/Admin/RolesController';
import { PageHeader } from '@ladcms/core/components/page-header';
import { Badge } from '@ladcms/core/components/ui/badge';
import { Button } from '@ladcms/core/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@ladcms/core/components/ui/dialog';
import { Input } from '@ladcms/core/components/ui/input';
import { Label } from '@ladcms/core/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@ladcms/core/components/ui/table';
import { roleName, useT } from '@ladcms/core/lib/i18n';

/** `RoleResource` с подсчётами. */
type RoleRow = {
    id: number;
    name: string;
    level: number | null;
    color: string | null;
    is_default: boolean;
    is_system: boolean;
    users_count: number;
    permissions_count: number;
};

type Props = {
    roles: RoleRow[];
    can: { create: boolean };
};

function FieldError({ message }: { message?: string }) {
    return message ? (
        <p className="text-sm text-destructive">{message}</p>
    ) : null;
}

function CreateRoleDialog() {
    const t = useT();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus aria-hidden />
                    {t('core::admin.roles.create')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <Form {...RolesController.store.form()} className="space-y-4">
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {t('core::admin.roles.create')}
                                </DialogTitle>
                                <DialogDescription>
                                    {t('core::admin.roles.create_description')}
                                </DialogDescription>
                            </DialogHeader>
                            <div className="grid gap-2">
                                <Label htmlFor="role-name">
                                    {t('core::admin.roles.name')}
                                </Label>
                                <Input
                                    id="role-name"
                                    name="name"
                                    autoComplete="off"
                                    required
                                    pattern="[a-z][a-z0-9_]*"
                                    aria-invalid={
                                        errors.name ? true : undefined
                                    }
                                />
                                <FieldError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="role-level">
                                    {t('core::admin.roles.level')}
                                </Label>
                                <Input
                                    id="role-level"
                                    name="level"
                                    type="number"
                                    min={0}
                                    max={6}
                                    placeholder={t(
                                        'core::admin.roles.level_hint',
                                    )}
                                    aria-invalid={
                                        errors.level ? true : undefined
                                    }
                                />
                                <FieldError message={errors.level} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="role-color">
                                    {t('core::admin.roles.color')}
                                </Label>
                                <Input
                                    id="role-color"
                                    name="color"
                                    type="color"
                                    className="h-9 w-20 p-1"
                                    defaultValue="#6366f1"
                                />
                                <FieldError message={errors.color} />
                            </div>
                            <FieldError message={errors.general} />
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {t('core::admin.roles.submit')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function RolesIndex({ roles, can }: Props) {
    const t = useT();

    return (
        <div className="space-y-6">
            <PageHeader
                title={t('core::admin.roles.title')}
                description={t('core::admin.roles.description')}
                actions={can.create ? <CreateRoleDialog /> : undefined}
            />

            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('core::admin.roles.name')}</TableHead>
                            <TableHead>
                                {t('core::admin.roles.level')}
                            </TableHead>
                            <TableHead>
                                {t('core::admin.roles.users')}
                            </TableHead>
                            <TableHead>
                                {t('core::admin.roles.permissions')}
                            </TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {roles.map((role) => (
                            <TableRow key={role.id}>
                                <TableCell>
                                    <Link
                                        href={RolesController.edit.url(role.id)}
                                        className="flex items-center gap-2 font-medium hover:underline"
                                    >
                                        <span
                                            aria-hidden
                                            className="size-2.5 rounded-full border"
                                            style={
                                                role.color
                                                    ? {
                                                          backgroundColor:
                                                              role.color,
                                                      }
                                                    : undefined
                                            }
                                        />
                                        {roleName(t, role.name)}
                                    </Link>
                                </TableCell>
                                <TableCell>
                                    {role.level ??
                                        t('core::admin.roles.no_level')}
                                </TableCell>
                                <TableCell>{role.users_count}</TableCell>
                                <TableCell>{role.permissions_count}</TableCell>
                                <TableCell>
                                    <div className="flex justify-end gap-1.5">
                                        {role.is_system && (
                                            <Badge variant="secondary">
                                                {t('core::admin.roles.system')}
                                            </Badge>
                                        )}
                                        {role.is_default && (
                                            <Badge variant="outline">
                                                {t('core::admin.roles.default')}
                                            </Badge>
                                        )}
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}

RolesIndex.layout = {
    title: 'core::admin.roles.title',
    breadcrumbs: [{ title: 'core::admin.roles.title' }],
};
