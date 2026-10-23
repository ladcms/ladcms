import { Link } from '@inertiajs/react';
import { Button } from '@ladcms/core/components/ui/button';
import {
    CardContent,
    CardHeader,
    CardTitle,
} from '@ladcms/core/components/ui/card';
import { useT } from '@ladcms/core/lib/i18n';

type Props = {
    site_name: string;
    description: string | null;
    rules_url: string;
};

export default function Welcome({ site_name, description, rules_url }: Props) {
    const t = useT();

    return (
        <section>
            <CardHeader className="p-4 pb-2">
                <CardTitle className="text-xl">
                    {t('core::public.widgets.welcome.title', {
                        name: site_name,
                    })}
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col items-start gap-3 p-4 pt-0">
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
                <Button asChild variant="outline" size="sm">
                    <Link href={rules_url}>
                        {t('core::public.widgets.welcome.rules')}
                    </Link>
                </Button>
            </CardContent>
        </section>
    );
}
