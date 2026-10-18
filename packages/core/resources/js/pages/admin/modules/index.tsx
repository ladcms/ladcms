import { router } from '@inertiajs/react';
import ModulesController from '@/actions/Ladcms/Core/Http/Controllers/Admin/ModulesController';
import { TriangleAlert } from 'lucide-react';
import { PageHeader } from '@ladcms/core/components/page-header';
import { Alert, AlertDescription } from '@ladcms/core/components/ui/alert';
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
import { useT } from '@ladcms/core/lib/i18n';

type ModuleStatus = 'active' | 'failed' | 'disabled' | 'not_installed';

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
    routes_cached: boolean;
};

export default function ModulesIndex({ modules, routes_cached }: Props) {
    const t = useT();
    const toggle = (name: string, isEnabled: boolean) => {
        router.patch(
            ModulesController.toggle.url(name),
            { is_enabled: isEnabled },
            { preserveScroll: true },
        );
    };

    return (
        <div className="space-y-6">
            <PageHeader title={t('core::admin.modules.title')} />

            {routes_cached && (
                <Alert variant="destructive">
                    <TriangleAlert />
                    <AlertDescription>
                        {t('core::admin.modules.routes_cached')}
                    </AlertDescription>
                </Alert>
            )}

            {modules.some((module) => module.needs_update) && (
                <p className="text-sm text-muted-foreground">
                    {t('core::admin.modules.update_hint')}{' '}
                    <code className="rounded bg-muted px-1.5 py-0.5">
                        php artisan ladcms:update
                    </code>
                </p>
            )}

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>
                            {t('core::admin.modules.columns.name')}
                        </TableHead>
                        <TableHead>
                            {t('core::admin.modules.columns.version')}
                        </TableHead>
                        <TableHead>
                            {t('core::admin.modules.columns.installed')}
                        </TableHead>
                        <TableHead>
                            {t('core::admin.modules.columns.status')}
                        </TableHead>
                        <TableHead>
                            {t('core::admin.modules.columns.enabled')}
                        </TableHead>
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
                                            {t('core::admin.modules.core')}
                                        </Badge>
                                    )}
                                </div>
                                {module.dependencies.length > 0 && (
                                    <div className="text-xs text-muted-foreground">
                                        {t('core::admin.modules.depends_on')}:{' '}
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
                                        {t('core::admin.modules.needs_update')}
                                    </div>
                                )}
                            </TableCell>
                            <TableCell className="align-top whitespace-normal">
                                <Badge variant={VARIANTS[module.status]}>
                                    {t(
                                        `core::admin.modules.statuses.${module.status}`,
                                    )}
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
    title: 'core::admin.modules.title',
    breadcrumbs: [{ title: 'core::admin.modules.title' }],
};
