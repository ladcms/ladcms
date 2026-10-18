import type { ReactNode } from 'react';

export type SortDirection = 'asc' | 'desc';

export type Column<T> = {
    key: string;
    label: string;
    sortable?: boolean;
    className?: string;
    render?(row: T): ReactNode;
};

export type PaginatorLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/** Shape of `LengthAwarePaginator::toArray()` (only the fields the table needs). */
export type Paginator = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginatorLink[];
};

export type TableSort = {
    column: string;
    direction: SortDirection;
};
