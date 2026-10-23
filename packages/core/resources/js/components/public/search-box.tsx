import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Input } from '@ladcms/core/components/ui/input';
import { resolveIcon } from '@ladcms/core/lib/icons';
import { useT } from '@ladcms/core/lib/i18n';
import { cn } from '@ladcms/core/lib/utils';
import { index as usersIndex } from '@/routes/users';

export function SearchBox({ className }: { className?: string }) {
    const t = useT();
    const [query, setQuery] = useState('');
    const SearchIcon = resolveIcon('search');

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const q = query.trim();
        router.get(usersIndex.url(), q === '' ? {} : { q });
    };

    return (
        <form
            role="search"
            onSubmit={submit}
            className={cn('relative', className)}
        >
            <SearchIcon
                aria-hidden
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                type="search"
                name="q"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder={t('core::public.header.search_placeholder')}
                aria-label={t('core::public.header.search')}
                className="rounded-full pl-9"
            />
        </form>
    );
}
