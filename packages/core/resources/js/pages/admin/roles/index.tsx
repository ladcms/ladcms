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

const t = {
    title: 'Роли',
    description: 'Роли, их уровни и права доступа.',
    create: 'Создать роль',
    createDescription:
        'Название — латиницей в нижнем регистре. Права назначаются на странице роли.',
    name: 'Название',
    level: 'Уровень',
    noLevel: '—',
    levelHint: '0–6, необязательно',
    color: 'Цвет',
    users: 'Пользователей',
    permissions: 'Прав',
    system: 'Системная',
    default: 'По умолчанию',
    submit: 'Создать',
};

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
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus aria-hidden />
                    {t.create}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <Form {...RolesController.store.form()} className="space-y-4">
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>{t.create}</DialogTitle>
                                <DialogDescription>
                                    {t.createDescription}
                                </DialogDescription>
                            </DialogHeader>
                            <div className="grid gap-2">
                                <Label htmlFor="role-name">{t.name}</Label>
                                <Input
                                    id="role-name"
                                    name="name"
                                    autoComplete="off"
                                    required
                                    pattern="[a-z][a-z0-9_]*"
                                    aria-invalid={errors.name ? true : undefined}
                                />
                                <FieldError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="role-level">{t.level}</Label>
                                <Input
                                    id="role-level"
                                    name="level"
                                    type="number"
                                    min={0}
                                    max={6}
                                    placeholder={t.levelHint}
                                    aria-invalid={
                                        errors.level ? true : undefined
                                    }
                                />
                                <FieldError message={errors.level} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="role-color">{t.color}</Label>
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
                                    {t.submit}
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

    return (
        <div className="space-y-6">
            <PageHeader
                title={t.title}
                description={t.description}
                actions={can.create ? <CreateRoleDialog /> : undefined}
            />

            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t.name}</TableHead>
                            <TableHead>{t.level}</TableHead>
                            <TableHead>{t.users}</TableHead>
                            <TableHead>{t.permissions}</TableHead>
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
                                        {role.name}
                                    </Link>
                                </TableCell>
                                <TableCell>{role.level ?? t.noLevel}</TableCell>
                                <TableCell>{role.users_count}</TableCell>
                                <TableCell>{role.permissions_count}</TableCell>
                                <TableCell>
                                    <div className="flex justify-end gap-1.5">
                                        {role.is_system && (
                                            <Badge variant="secondary">
                                                {t.system}
                                            </Badge>
                                        )}
                                        {role.is_default && (
                                            <Badge variant="outline">
                                                {t.default}
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
    title: t.title,
    breadcrumbs: [{ title: t.title }],
};
