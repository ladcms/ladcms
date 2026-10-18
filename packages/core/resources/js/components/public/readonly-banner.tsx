import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@ladcms/core/components/ui/alert';
import { useT } from '@ladcms/core/lib/i18n';

export function ReadonlyBanner() {
    const t = useT();

    return (
        <Alert role="status" className="rounded-none border-x-0 border-t-0">
            <AlertTitle>{t('core::public.readonly.title')}</AlertTitle>
            <AlertDescription>
                {t('core::public.readonly.text')}
            </AlertDescription>
        </Alert>
    );
}
