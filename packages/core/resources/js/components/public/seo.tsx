import { Head, usePage } from '@inertiajs/react';
import type { LayoutShared } from '@ladcms/core/types/layout';

type SeoProps = {
    /** Заголовок страницы; пусто — название сайта. */
    title?: string | null;
    /** Описание; по умолчанию — описание сайта. */
    description?: string | null;
    /** Картинка для превью (абсолютный или корневой путь); по умолчанию — логотип сайта. */
    image?: string | null;
    /** `og:type`: `website` или `profile`. */
    type?: 'website' | 'profile';
};

/** Склеивает корень сайта (`site.url`) и путь; абсолютные ссылки не трогает. */
function absolute(base: string | undefined, path: string): string {
    if (/^https?:\/\//i.test(path) || !base) {
        return path;
    }

    return `${base}${path.startsWith('/') ? '' : '/'}${path}`;
}

/** `<Head>` с `title`, `description` и Open Graph мета (`og:url` — абсолютный: `site.url` + текущий путь). */
export function Seo({ title, description, image, type = 'website' }: SeoProps) {
    const { props, url } = usePage<{ layout?: LayoutShared | null }>();
    const site = props.layout?.site;

    const pageTitle = title || site?.name || undefined;
    const pageDescription = description || site?.description || undefined;
    const pageImage = image || site?.logo_url || undefined;

    return (
        <Head title={pageTitle}>
            {pageDescription && (
                <meta
                    head-key="description"
                    name="description"
                    content={pageDescription}
                />
            )}
            {pageTitle && (
                <meta
                    head-key="og:title"
                    property="og:title"
                    content={pageTitle}
                />
            )}
            {pageDescription && (
                <meta
                    head-key="og:description"
                    property="og:description"
                    content={pageDescription}
                />
            )}
            <meta head-key="og:type" property="og:type" content={type} />
            {site?.url && (
                <meta
                    head-key="og:url"
                    property="og:url"
                    content={absolute(site.url, url.split('#')[0])}
                />
            )}
            {site?.name && (
                <meta
                    head-key="og:site_name"
                    property="og:site_name"
                    content={site.name}
                />
            )}
            {pageImage && (
                <meta
                    head-key="og:image"
                    property="og:image"
                    content={absolute(site?.url, pageImage)}
                />
            )}
        </Head>
    );
}
