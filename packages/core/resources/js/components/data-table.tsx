import { Link, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyState } from '@ladcms/core/components/empty-state';
import { buttonVariants } from '@ladcms/core/components/ui/button';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
} from '@ladcms/core/components/ui/pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@ladcms/core/components/ui/table';
import { useT } from '@ladcms/core/lib/i18n';
import { cn } from '@ladcms/core/lib/utils';
import type {
    Column,
    Paginator,
    PaginatorLink,
    SortDirection,
    TableSort,
} from '@ladcms/core/types/table';

type DataTableProps<T extends object> = {
    columns: Column<T>[];
    rows: T[];
    pagination: Paginator;
    sort?: TableSort;
    onSort?: (column: string) => void;
    emptyText?: ReactNode;
    rowKey?: (row: T, index: number) => string | number;
};

type QueryParams = Record<string, string | number | null>;

/** Returns the current page URL (path + query) with the given params replaced; `null` removes a param. */
function urlWith(currentUrl: string, params: QueryParams): string {
    const url = new URL(currentUrl, 'http://localhost');

    for (const [key, value] of Object.entries(params)) {
        if (value === null) {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, String(value));
        }
    }

    return `${url.pathname}${url.search}`;
}

function pageFromLink(link: PaginatorLink): string | null {
    if (link.url === null) {
        return null;
    }

    return new URL(link.url, 'http://localhost').searchParams.get('page');
}

function nextDirection(column: string, sort?: TableSort): SortDirection {
    return sort?.column === column && sort.direction === 'asc' ? 'desc' : 'asc';
}

function stripEntities(label: string): string {
    return label.replace(/&[a-z]+;/gi, '').trim();
}

function cellValue<T extends object>(row: T, key: string): ReactNode {
    const value: unknown = (row as Record<string, unknown>)[key];

    if (value === null || value === undefined) {
        return '—';
    }

    if (typeof value === 'string' || typeof value === 'number') {
        return value;
    }

    if (typeof value === 'boolean') {
        return value ? '✓' : '—';
    }

    return JSON.stringify(value);
}

function SortIcon({
    active,
    direction,
}: {
    active: boolean;
    direction?: SortDirection;
}) {
    if (!active) {
        return <ArrowUpDown className="size-3.5 opacity-50" />;
    }

    return direction === 'desc' ? (
        <ArrowDown className="size-3.5" />
    ) : (
        <ArrowUp className="size-3.5" />
    );
}

export function DataTablePagination({
    pagination,
    currentUrl,
}: {
    pagination: Paginator;
    currentUrl: string;
}) {
    if (pagination.last_page <= 1) {
        return null;
    }

    const lastIndex = pagination.links.length - 1;

    return (
        <Pagination className="justify-end">
            <PaginationContent>
                {pagination.links.map((link, index) => {
                    const page = pageFromLink(link);
                    const isPrev = index === 0;
                    const isNext = index === lastIndex;

                    if (!isPrev && !isNext && page === null) {
                        return (
                            <PaginationItem key={`ellipsis-${index}`}>
                                <PaginationEllipsis />
                            </PaginationItem>
                        );
                    }

                    const content = isPrev ? (
                        <ChevronLeft />
                    ) : isNext ? (
                        <ChevronRight />
                    ) : (
                        link.label
                    );
                    const className = cn(
                        buttonVariants({
                            variant: link.active ? 'outline' : 'ghost',
                            size: 'icon',
                        }),
                        'rounded-full',
                        page === null && 'pointer-events-none opacity-50',
                    );

                    return (
                        <PaginationItem key={`${index}-${link.label}`}>
                            {page === null ? (
                                <span
                                    aria-disabled
                                    aria-label={stripEntities(link.label)}
                                    className={className}
                                >
                                    {content}
                                </span>
                            ) : (
                                <Link
                                    href={urlWith(currentUrl, { page })}
                                    preserveState
                                    preserveScroll
                                    aria-current={
                                        link.active ? 'page' : undefined
                                    }
                                    aria-label={stripEntities(link.label)}
                                    className={className}
                                >
                                    {content}
                                </Link>
                            )}
                        </PaginationItem>
                    );
                })}
            </PaginationContent>
        </Pagination>
    );
}

export function DataTable<T extends object>({
    columns,
    rows,
    pagination,
    sort,
    onSort,
    emptyText,
    rowKey,
}: DataTableProps<T>) {
    const t = useT();
    const currentUrl = usePage().url;

    const renderHead = (column: Column<T>) => {
        if (!column.sortable) {
            return column.label;
        }

        const active = sort?.column === column.key;
        const label = (
            <>
                {column.label}
                <SortIcon active={active} direction={sort?.direction} />
            </>
        );
        const className =
            '-ml-2 inline-flex items-center gap-1.5 rounded-md px-2 py-1 hover:bg-accent hover:text-accent-foreground';

        if (onSort) {
            return (
                <button
                    type="button"
                    className={className}
                    onClick={() => onSort(column.key)}
                >
                    {label}
                </button>
            );
        }

        return (
            <Link
                href={urlWith(currentUrl, {
                    sort: column.key,
                    direction: nextDirection(column.key, sort),
                    page: null,
                })}
                preserveState
                preserveScroll
                className={className}
            >
                {label}
            </Link>
        );
    };

    return (
        <div className="space-y-4">
            <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                {rows.length === 0 ? (
                    <EmptyState
                        title={emptyText ?? t('core::admin.table.empty')}
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                {columns.map((column) => (
                                    <TableHead
                                        key={column.key}
                                        className={cn('px-4', column.className)}
                                        aria-sort={
                                            sort?.column === column.key
                                                ? sort.direction === 'asc'
                                                    ? 'ascending'
                                                    : 'descending'
                                                : undefined
                                        }
                                    >
                                        {renderHead(column)}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((row, index) => (
                                <TableRow
                                    key={rowKey ? rowKey(row, index) : index}
                                >
                                    {columns.map((column) => (
                                        <TableCell
                                            key={column.key}
                                            className={cn(
                                                'px-4',
                                                column.className,
                                            )}
                                        >
                                            {column.render
                                                ? column.render(row)
                                                : cellValue(row, column.key)}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>
            <DataTablePagination
                pagination={pagination}
                currentUrl={currentUrl}
            />
        </div>
    );
}
