import { Input } from '@ladcms/core/components/ui/input';
import { Label } from '@ladcms/core/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@ladcms/core/components/ui/select';
import { Switch } from '@ladcms/core/components/ui/switch';
import { Textarea } from '@ladcms/core/components/ui/textarea';
import { ViolationCodesEditor } from './violation-codes-editor';
import type { ViolationCodeRow } from './violation-codes-editor';

export type FieldType =
    | 'text'
    | 'textarea'
    | 'markdown'
    | 'number'
    | 'boolean'
    | 'select'
    | 'color'
    | 'json'
    | 'violation_codes';

/** Значение поля формы настроек (совместимо с FormDataConvertible Inertia). */
export type FieldValue =
    | string
    | number
    | boolean
    | null
    | { [key: string]: string | number | null }[];

/** Поле схемы (`Ladcms\Core\Settings\Schema\SettingsSchema`). */
export type SchemaField = {
    name: string;
    type: FieldType;
    label: string;
    description: string | null;
    nullable: boolean;
    options: { value: string; label: string }[] | null;
    min: number | null;
    max: number | null;
    value: FieldValue;
};

type Props = {
    field: SchemaField;
    value: FieldValue;
    onChange: (value: FieldValue) => void;
    errors: Record<string, string>;
};

/** Значение для формы: JSON-поле редактируется как текст, остальное — как есть. */
export function initialValue(field: SchemaField): FieldValue {
    if (field.type === 'json') {
        return JSON.stringify(field.value, null, 2);
    }

    return field.value ?? (field.type === 'boolean' ? false : '');
}

export function FieldRenderer({ field, value, onChange, errors }: Props) {
    const id = `setting-${field.name}`;
    const error = errors[field.name];
    const text = typeof value === 'string' ? value : '';

    const control = () => {
        switch (field.type) {
            case 'boolean':
                return (
                    <Switch
                        id={id}
                        checked={value === true}
                        onCheckedChange={onChange}
                    />
                );
            case 'number':
                return (
                    <Input
                        id={id}
                        type="number"
                        min={field.min ?? undefined}
                        max={field.max ?? undefined}
                        value={typeof value === 'number' ? String(value) : text}
                        onChange={(event) => onChange(event.target.value)}
                    />
                );
            case 'select':
                return (
                    <Select value={text} onValueChange={onChange}>
                        <SelectTrigger id={id} className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {(field.options ?? []).map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                );
            case 'color':
                return (
                    <div className="flex items-center gap-2">
                        <input
                            type="color"
                            aria-label={field.label}
                            className="h-9 w-12 cursor-pointer rounded-md border bg-transparent p-1"
                            value={
                                /^#[0-9a-fA-F]{6}$/.test(text)
                                    ? text
                                    : '#000000'
                            }
                            onChange={(event) => onChange(event.target.value)}
                        />
                        <Input
                            id={id}
                            value={text}
                            placeholder="#rrggbb"
                            autoComplete="off"
                            onChange={(event) => onChange(event.target.value)}
                        />
                    </div>
                );
            case 'textarea':
                return (
                    <Textarea
                        id={id}
                        rows={3}
                        value={text}
                        onChange={(event) => onChange(event.target.value)}
                    />
                );
            case 'markdown':
            case 'json':
                return (
                    <Textarea
                        id={id}
                        rows={field.type === 'json' ? 8 : 10}
                        className="font-mono text-sm"
                        value={text}
                        onChange={(event) => onChange(event.target.value)}
                    />
                );
            case 'violation_codes':
                return (
                    <ViolationCodesEditor
                        name={field.name}
                        value={
                            Array.isArray(value)
                                ? (value as ViolationCodeRow[])
                                : []
                        }
                        onChange={onChange}
                        errors={errors}
                    />
                );
            default:
                return (
                    <Input
                        id={id}
                        value={text}
                        autoComplete="off"
                        onChange={(event) => onChange(event.target.value)}
                    />
                );
        }
    };

    const inline = field.type === 'boolean';

    return (
        <div className="grid gap-2">
            <div
                className={
                    inline
                        ? 'flex items-center justify-between gap-4'
                        : 'grid gap-2'
                }
            >
                <div className="grid gap-1">
                    <Label htmlFor={id}>{field.label}</Label>
                    {field.description && (
                        <p className="text-sm text-muted-foreground">
                            {field.description}
                        </p>
                    )}
                </div>
                {inline && control()}
            </div>
            {!inline && control()}
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}
