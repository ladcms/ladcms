import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import SettingsController from '@/actions/Ladcms/Core/Http/Controllers/Admin/SettingsController';
import {
    FieldRenderer,
    initialValue,
} from '@ladcms/core/components/admin/settings/field-renderer';
import type {
    FieldValue,
    SchemaField,
} from '@ladcms/core/components/admin/settings/field-renderer';
import { PageHeader } from '@ladcms/core/components/page-header';
import { Button } from '@ladcms/core/components/ui/button';
import { Card, CardContent, CardFooter } from '@ladcms/core/components/ui/card';
import { cn } from '@ladcms/core/lib/utils';
import type { AdminBreadcrumb } from '@ladcms/core/types/admin';
import { useT } from '@ladcms/core/lib/i18n';

type Props = {
    group: string;
    groups: { key: string; label: string; href: string }[];
    schema: { group: string; label: string; fields: SchemaField[] };
};

type LayoutProps = { title: string; breadcrumbs: AdminBreadcrumb[] };

export default function SettingsEdit({ group, groups, schema }: Props) {
    const t = useT();
    const form = useForm<Record<string, FieldValue>>(
        Object.fromEntries(
            schema.fields.map((field) => [field.name, initialValue(field)]),
        ),
    );

    useEffect(() => {
        setLayoutProps<LayoutProps>({
            title: schema.label,
            breadcrumbs: [
                { title: t('core::admin.settings.title') },
                { title: schema.label },
            ],
        });
    }, [schema.label]);

    const errors = form.errors as Record<string, string>;

    return (
        <div className="space-y-6">
            <Head
                title={`${t('core::admin.settings.title')}: ${schema.label}`}
            />
            <PageHeader title={t('core::admin.settings.title')} />

            <nav
                aria-label={t('core::admin.settings.tabs')}
                className="flex flex-wrap gap-1 border-b"
            >
                {groups.map((item) => (
                    <Link
                        key={item.key}
                        href={item.href}
                        aria-current={item.key === group ? 'page' : undefined}
                        className={cn(
                            '-mb-px border-b-2 px-3 py-2 text-sm font-medium',
                            item.key === group
                                ? 'border-primary text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {item.label}
                    </Link>
                ))}
            </nav>

            <Card>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(SettingsController.update.url({ group }), {
                            preserveScroll: true,
                        });
                    }}
                >
                    <CardContent className="grid gap-6 sm:max-w-3xl">
                        {schema.fields.map((field) => (
                            <FieldRenderer
                                key={field.name}
                                field={field}
                                value={form.data[field.name]}
                                onChange={(value) =>
                                    form.setData(field.name, value)
                                }
                                errors={errors}
                            />
                        ))}
                    </CardContent>
                    <CardFooter className="justify-end pt-6">
                        <Button type="submit" disabled={form.processing}>
                            {t('core::admin.settings.save')}
                        </Button>
                    </CardFooter>
                </form>
            </Card>
        </div>
    );
}

SettingsEdit.layout = {
    title: 'core::admin.settings.title',
    breadcrumbs: [{ title: 'core::admin.settings.title' }],
};
