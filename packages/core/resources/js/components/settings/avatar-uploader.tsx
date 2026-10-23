import { useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import { ConfirmDialog } from '@ladcms/core/components/confirm-dialog';
import {
    Avatar,
    AvatarFallback,
    AvatarImage,
} from '@ladcms/core/components/ui/avatar';
import { Button } from '@ladcms/core/components/ui/button';
import { useT } from '@ladcms/core/lib/i18n';
import { initials } from '@ladcms/core/lib/initials';
import AvatarController from '@/actions/Ladcms/Core/Http/Controllers/Settings/AvatarController';

const ACCEPT = 'image/jpeg,image/png,image/webp';

function formatSize(kb: number): string {
    return kb >= 1024 ? `${Math.round((kb / 1024) * 10) / 10} MB` : `${kb} KB`;
}

/** Загрузка/удаление аватара: предпросмотр выбранного файла, прогресс, ошибки валидации сервера. */
export function AvatarUploader({
    name,
    avatarUrl,
    maxKb,
    maxDimension,
}: {
    name: string;
    avatarUrl: string | null;
    maxKb: number;
    maxDimension: number;
}) {
    const t = useT();
    const inputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const form = useForm<{ avatar: File | null }>({ avatar: null });
    const removeForm = useForm({});

    // Освобождаем blob-URL при смене файла и размонтировании.
    useEffect(() => {
        if (!preview) {
            return;
        }

        return () => URL.revokeObjectURL(preview);
    }, [preview]);

    const reset = () => {
        setPreview(null);
        form.setData('avatar', null);
        form.clearErrors();

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    };

    const onChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;

        form.clearErrors();
        form.setData('avatar', file);
        setPreview(file ? URL.createObjectURL(file) : null);
    };

    const upload = () => {
        form.post(AvatarController.store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: reset,
        });
    };

    const remove = () => {
        removeForm.delete(AvatarController.destroy.url(), {
            preserveScroll: true,
        });
    };

    const shown = preview ?? avatarUrl;
    const percentage = form.progress?.percentage ?? 0;

    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
            <Avatar className="size-24">
                {shown && <AvatarImage src={shown} alt={name} />}
                <AvatarFallback className="text-2xl">
                    {initials(name)}
                </AvatarFallback>
            </Avatar>

            <div className="grid gap-2">
                <input
                    ref={inputRef}
                    type="file"
                    accept={ACCEPT}
                    className="hidden"
                    aria-label={t(
                        'core::public.settings.profile.avatar_choose',
                    )}
                    onChange={onChange}
                />
                <div className="flex flex-wrap gap-2">
                    {preview ? (
                        <>
                            <Button
                                type="button"
                                onClick={upload}
                                disabled={form.processing}
                            >
                                {t(
                                    'core::public.settings.profile.avatar_upload',
                                )}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={reset}
                                disabled={form.processing}
                            >
                                {t(
                                    'core::public.settings.profile.avatar_cancel',
                                )}
                            </Button>
                        </>
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => inputRef.current?.click()}
                        >
                            {t('core::public.settings.profile.avatar_choose')}
                        </Button>
                    )}
                    {avatarUrl && !preview && (
                        <ConfirmDialog
                            destructive
                            trigger={
                                <Button
                                    type="button"
                                    variant="ghost"
                                    disabled={removeForm.processing}
                                >
                                    {t(
                                        'core::public.settings.profile.avatar_remove',
                                    )}
                                </Button>
                            }
                            title={t(
                                'core::public.settings.profile.avatar_remove_title',
                            )}
                            description={t(
                                'core::public.settings.profile.avatar_remove_description',
                            )}
                            confirmLabel={t(
                                'core::public.settings.profile.avatar_remove',
                            )}
                            onConfirm={remove}
                        />
                    )}
                </div>

                {form.processing && (
                    <div
                        role="progressbar"
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-valuenow={percentage}
                        className="h-2 w-full max-w-xs overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            className="h-full bg-primary transition-all"
                            style={{ width: `${percentage}%` }}
                        />
                    </div>
                )}

                <p className="text-sm text-muted-foreground">
                    {t('core::public.settings.profile.avatar_hint', {
                        size: formatSize(maxKb),
                        dim: maxDimension,
                    })}
                </p>
                {form.errors.avatar && (
                    <p role="alert" className="text-sm text-destructive">
                        {form.errors.avatar}
                    </p>
                )}
            </div>
        </div>
    );
}
