import { ScrollText } from 'lucide-react';
import { EmptyState } from '@ladcms/core/components/empty-state';
import { PageHeader } from '@ladcms/core/components/page-header';
import { Seo } from '@ladcms/core/components/public/seo';
import { useT } from '@ladcms/core/lib/i18n';

type Props = {
    /** HTML, санитизированный на сервере (`Str::markdown` с `html_input: strip`); null — правила не заполнены. */
    html: string | null;
};

export default function Rules({ html }: Props) {
    const t = useT();

    return (
        <>
            <Seo title={t('core::public.rules.title')} />
            <div className="space-y-6">
                <PageHeader title={t('core::public.rules.title')} />
                {html === null ? (
                    <EmptyState
                        icon={ScrollText}
                        title={t('core::public.rules.empty')}
                        description={t('core::public.rules.empty_text')}
                    />
                ) : (
                    <div
                        className="prose max-w-none dark:prose-invert"
                        // Безопасно: HTML очищен на сервере, сырой HTML и unsafe-ссылки вырезаны.
                        dangerouslySetInnerHTML={{ __html: html }}
                    />
                )}
            </div>
        </>
    );
}
