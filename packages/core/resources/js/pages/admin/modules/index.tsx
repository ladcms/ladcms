import { router } from '@inertiajs/react';
import ModulesController from '@/actions/Ladcms/Core/Http/Controllers/Admin/ModulesController';
import { PageHeader } from '@ladcms/core/components/page-header';
import { Badge } from '@ladcms/core/components/ui/badge';
import { Switch } from '@ladcms/core/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@ladcms/core/components/ui/table';

type ModuleStatus = 'active' | 'failed' | 'disabled' | 'not_installed';

const t = {
    title: 'Модули',
    updateHint: 'Для обновления модулей выполните',
    columns: {
        name: 'Модуль',
        version: 'Версия',
        installed: 'Установлена',
        status: 'Статус',
        enabled: 'Включён',
    },
    statuses: {
        active: 'Активен',
        failed: 'Ошибка',
        disabled: 'Выключен',
        not_installed: 'Не установлен',
    } satisfies Record<ModuleStatus, string>,
    needsUpdate: 'требуется обновление',
    core: 'ядро',
    dependsOn: 'Зависит от',
};

const VARIANTS: Record<
    ModuleStatus,
    'default' | 'destructive' | 'secondary' | 'outline'
> = {
    active: 'default',
    failed: 'destructive',
    disabled: 'secondary',
    not_installed: 'outline',
};

type ModuleRow = {
    name: string;
    version: string | null;
    installed_version: string | null;
    is_enabled: boolean;
    status: ModuleStatus;
    reason: string | null;
    needs_update: boolean;
    is_core: boolean;
    dependencies: string[];
};

type Props = {
    modules: ModuleRow[];
};

export default function ModulesIndex({ modules }: Props) {
    const toggle = (name: string, isEnabled: boolean) => {
        router.patch(
            ModulesController.toggle.url(name),
            { is_enabled: isEnabled },
            { preserveScroll: true },
        );
    };

    return (
        <div className="space-y-6">
            <PageHeader title={t.title} />

            {modules.some((module) => module.needs_update) && (
                <p className="text-sm text-muted-foreground">
                    {t.updateHint}{' '}
                    <code className="rounded bg-muted px-1.5 py-0.5">
                        php artisan ladcms:update
                    </code>
                </p>
            )}

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{t.columns.name}</TableHead>
                        <TableHead>{t.columns.version}</TableHead>
                        <TableHead>{t.columns.installed}</TableHead>
                        <TableHead>{t.columns.status}</TableHead>
                        <TableHead>{t.columns.enabled}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {modules.map((module) => (
                        <TableRow key={module.name}>
                            <TableCell className="align-top">
                                <div className="font-medium">
                                    {module.name}
                                    {module.is_core && (
                                        <Badge
                                            variant="outline"
                                            className="ml-2"
                                        >
                                            {t.core}
                                        </Badge>
                                    )}
                                </div>
                                {module.dependencies.length > 0 && (
                                    <div className="text-xs text-muted-foreground">
                                        {t.dependsOn}:{' '}
                                        {module.dependencies.join(', ')}
                                    </div>
                                )}
                            </TableCell>
                            <TableCell className="align-top">
                                {module.version ?? '—'}
                            </TableCell>
                            <TableCell className="align-top">
                                {module.installed_version ?? '—'}
                                {module.needs_update && (
                                    <div className="text-xs text-amber-600">
                                        {t.needsUpdate}
                                    </div>
                                )}
                            </TableCell>
                            <TableCell className="align-top whitespace-normal">
                                <Badge variant={VARIANTS[module.status]}>
                                    {t.statuses[module.status]}
                                </Badge>
                                {module.reason !== null && (
                                    <div className="mt-1 max-w-md text-xs break-words text-muted-foreground">
                                        {module.reason}
                                    </div>
                                )}
                            </TableCell>
                            <TableCell className="align-top">
                                <Switch
                                    aria-label={module.name}
                                    checked={module.is_enabled}
                                    disabled={
                                        module.is_core ||
                                        module.status === 'not_installed'
                                    }
                                    onCheckedChange={(checked) =>
                                        toggle(module.name, checked)
                                    }
                                />
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

ModulesIndex.layout = {
    title: t.title,
    breadcrumbs: [{ title: t.title }],
};
