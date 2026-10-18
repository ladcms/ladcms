import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { DataTable } from '@ladcms/core/components/data-table';
import { PageHeader } from '@ladcms/core/components/page-header';
import { Button } from '@ladcms/core/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@ladcms/core/components/ui/collapsible';
import { Input } from '@ladcms/core/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@ladcms/core/components/ui/select';
import type {
    Column,
    Paginator,
    SortDirection,
} from '@ladcms/core/types/table';

const ALL = '__all__';

const t = {
    title: 'Журнал действий',
    columns: {
        created_at: 'Время',
        causer: 'Кто',
        event: 'Событие',
        subject: 'Субъект',
        properties: 'Свойства',
    },
    causer: 'Кто (логин)',
    event: 'Событие',
    allEvents: 'Все события',
    from: 'С',
    to: 'По',
    apply: 'Применить',
    reset: 'Сбросить',
    show: 'Показать',
    empty: 'Записей нет',
    system: 'система',
};

type ActivityRow = {
    id: number;
    event: string | null;
    causer: { id: number; login: string } | null;
    subject: { type?: string; id?: number; login?: string } | null;
    properties: Record<string, unknown> | unknown[] | null;
    created_at: string | null;
};

type Filters = {
    causer: string | null;
    event: string | null;
    from: string | null;
    to: string | null;
    sort: string;
    direction: SortDirection;
};

type Props = {
    activity: { data: ActivityRow[]; meta: Paginator };
    filters: Filters;
    events: string[];
};

function hasProperties(properties: ActivityRow['properties']): boolean {
    if (properties === null || properties === undefined) {
        return false;
    }

    return Object.keys(properties).length > 0;
}

export default function ActivityIndex({ activity, filters, events }: Props) {
    const { url } = usePage();
    const path = url.split('?')[0] ?? url;

    const [causer, setCauser] = useState(filters.causer ?? '');
    const [event, setEvent] = useState(filters.event ?? ALL);
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    const visit = (data: Record<string, string>) => {
        const query = Object.fromEntries(
            Object.entries(data).filter(([, value]) => value !== ''),
        );
        router.get(path, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        visit({
            causer,
            event: event === ALL ? '' : event,
            from,
            to,
            sort: filters.sort,
            direction: filters.direction,
        });
    };

    const reset = () => {
        setCauser('');
        setEvent(ALL);
        setFrom('');
        setTo('');
        visit({});
    };

    const columns: Column<ActivityRow>[] = [
        {
            key: 'created_at',
            label: t.columns.created_at,
            sortable: true,
            className: 'whitespace-nowrap',
            render: (row) =>
                row.created_at === null
                    ? '—'
                    : new Date(row.created_at).toLocaleString(),
        },
        {
            key: 'causer',
            label: t.columns.causer,
            render: (row) => row.causer?.login ?? t.system,
        },
        {
            key: 'event',
            label: t.columns.event,
            sortable: true,
            render: (row) => <code>{row.event}</code>,
        },
        {
            key: 'subject',
            label: t.columns.subject,
            render: (row) =>
                row.subject === null
                    ? '—'
                    : [
                          row.subject.type,
                          row.subject.id === undefined
                              ? null
                              : `#${row.subject.id}`,
                          row.subject.login,
                      ]
                          .filter(Boolean)
                          .join(' '),
        },
        {
            key: 'properties',
            label: t.columns.properties,
            className: 'max-w-md whitespace-normal',
            render: (row) =>
                hasProperties(row.properties) ? (
                    <Collapsible>
                        <CollapsibleTrigger asChild>
                            <Button size="sm" variant="ghost">
                                {t.show}
                            </Button>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <pre className="mt-2 overflow-x-auto rounded bg-muted p-2 text-xs">
                                {JSON.stringify(row.properties, null, 2)}
                            </pre>
                        </CollapsibleContent>
                    </Collapsible>
                ) : (
                    '—'
                ),
        },
    ];

    return (
        <div className="space-y-6">
            <PageHeader title={t.title} />

            <form
                onSubmit={submit}
                className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end"
            >
                <Input
                    aria-label={t.causer}
                    placeholder={t.causer}
                    value={causer}
                    onChange={(e) => setCauser(e.target.value)}
                    className="sm:w-48"
                />
                <Select value={event} onValueChange={setEvent}>
                    <SelectTrigger aria-label={t.event} className="sm:w-56">
                        <SelectValue placeholder={t.event} />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>{t.allEvents}</SelectItem>
                        {events.map((name) => (
                            <SelectItem key={name} value={name}>
                                {name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Input
                    type="date"
                    aria-label={t.from}
                    value={from}
                    onChange={(e) => setFrom(e.target.value)}
                    className="sm:w-40"
                />
                <Input
                    type="date"
                    aria-label={t.to}
                    value={to}
                    onChange={(e) => setTo(e.target.value)}
                    className="sm:w-40"
                />
                <Button type="submit">{t.apply}</Button>
                <Button type="button" variant="outline" onClick={reset}>
                    {t.reset}
                </Button>
            </form>

            <DataTable
                columns={columns}
                rows={activity.data}
                pagination={activity.meta}
                sort={{ column: filters.sort, direction: filters.direction }}
                emptyText={t.empty}
                rowKey={(row) => row.id}
            />
        </div>
    );
}

ActivityIndex.layout = {
    title: t.title,
    breadcrumbs: [{ title: t.title }],
};
