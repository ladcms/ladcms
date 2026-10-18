import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@ladcms/core/components/ui/button';
import { Input } from '@ladcms/core/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@ladcms/core/components/ui/table';
import { useT } from '@ladcms/core/lib/i18n';

/** Строка настройки `violation_codes`; пустые числа — пустая строка до отправки. */
export type ViolationCodeRow = {
    code: string;
    label: string;
    min_days: number | string;
    max_days: number | string | null;
};

type Props = {
    name: string;
    value: ViolationCodeRow[];
    onChange: (rows: ViolationCodeRow[]) => void;
    errors: Record<string, string>;
};

export function ViolationCodesEditor({ name, value, onChange, errors }: Props) {
    const t = useT();
    const update = (index: number, patch: Partial<ViolationCodeRow>) =>
        onChange(
            value.map((row, i) => (i === index ? { ...row, ...patch } : row)),
        );

    const rowErrors = (index: number) =>
        Object.entries(errors)
            .filter(([key]) => key.startsWith(`${name}.${index}.`))
            .map(([, message]) => message);

    return (
        <div className="space-y-3">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>
                            {t('core::admin.settings.violation_codes.code')}
                        </TableHead>
                        <TableHead>
                            {t('core::admin.settings.violation_codes.label')}
                        </TableHead>
                        <TableHead className="w-28">
                            {t('core::admin.settings.violation_codes.min_days')}
                        </TableHead>
                        <TableHead className="w-28">
                            {t('core::admin.settings.violation_codes.max_days')}
                        </TableHead>
                        <TableHead className="w-12" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {value.length === 0 && (
                        <TableRow>
                            <TableCell
                                colSpan={5}
                                className="text-center text-muted-foreground"
                            >
                                {t(
                                    'core::admin.settings.violation_codes.empty',
                                )}
                            </TableCell>
                        </TableRow>
                    )}
                    {value.map((row, index) => (
                        <TableRow key={index} className="align-top">
                            <TableCell>
                                <Input
                                    aria-label={t(
                                        'core::admin.settings.violation_codes.code',
                                    )}
                                    value={row.code}
                                    onChange={(event) =>
                                        update(index, {
                                            code: event.target.value,
                                        })
                                    }
                                    autoComplete="off"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    aria-label={t(
                                        'core::admin.settings.violation_codes.label',
                                    )}
                                    value={row.label}
                                    onChange={(event) =>
                                        update(index, {
                                            label: event.target.value,
                                        })
                                    }
                                    autoComplete="off"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    aria-label={t(
                                        'core::admin.settings.violation_codes.min_days',
                                    )}
                                    type="number"
                                    min={0}
                                    value={row.min_days}
                                    onChange={(event) =>
                                        update(index, {
                                            min_days: event.target.value,
                                        })
                                    }
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    aria-label={t(
                                        'core::admin.settings.violation_codes.max_days',
                                    )}
                                    type="number"
                                    min={0}
                                    placeholder={t(
                                        'core::admin.settings.violation_codes.max_hint',
                                    )}
                                    value={row.max_days ?? ''}
                                    onChange={(event) =>
                                        update(index, {
                                            max_days: event.target.value,
                                        })
                                    }
                                />
                            </TableCell>
                            <TableCell>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t(
                                        'core::admin.settings.violation_codes.remove',
                                    )}
                                    onClick={() =>
                                        onChange(
                                            value.filter((_, i) => i !== index),
                                        )
                                    }
                                >
                                    <Trash2 aria-hidden />
                                </Button>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            {value.flatMap((_, index) =>
                rowErrors(index).map((message, i) => (
                    <p
                        key={`${index}-${i}`}
                        className="text-sm text-destructive"
                    >
                        #{index + 1}: {message}
                    </p>
                )),
            )}
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() =>
                    onChange([
                        ...value,
                        { code: '', label: '', min_days: 0, max_days: null },
                    ])
                }
            >
                <Plus aria-hidden />
                {t('core::admin.settings.violation_codes.add')}
            </Button>
        </div>
    );
}
