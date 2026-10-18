import type { ReactNode } from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@ladcms/core/components/ui/alert-dialog';
import { useT } from '@ladcms/core/lib/i18n';

type ConfirmDialogProps = {
    trigger: ReactNode;
    title: ReactNode;
    description: ReactNode;
    confirmLabel: ReactNode;
    cancelLabel?: ReactNode;
    destructive?: boolean;
    onConfirm: () => void;
};

export function ConfirmDialog({
    trigger,
    title,
    description,
    confirmLabel,
    cancelLabel,
    destructive = false,
    onConfirm,
}: ConfirmDialogProps) {
    const t = useT();

    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>{trigger}</AlertDialogTrigger>
            <AlertDialogContent className="rounded-xl">
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {description}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel className="rounded-full">
                        {cancelLabel ?? t('core::admin.common.cancel')}
                    </AlertDialogCancel>
                    <AlertDialogAction
                        variant={destructive ? 'destructive' : 'default'}
                        className="rounded-full"
                        onClick={() => onConfirm()}
                    >
                        {confirmLabel}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
