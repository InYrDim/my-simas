import {
    DndContext,
    KeyboardSensor,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors,
    type DragEndEvent,
} from '@dnd-kit/core';
import {
    SortableContext,
    arrayMove,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { router, useForm } from '@inertiajs/react';
import {
    ArchiveIcon,
    ArchiveRestoreIcon,
    ArrowDownIcon,
    ArrowUpIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    GripVerticalIcon,
    PlusIcon,
    XIcon,
} from 'lucide-react';
import { useEffect, useState } from 'react';

import {
    destroy,
    update,
} from '@/actions/Modules/Ppdb/App/Http/Controllers/FormController';
import { form as formRoute } from '@/routes/ppdb';
import { EmptyState, OptionSelect, Panel } from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Checkbox } from '@shared/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@shared/components/ui/dropdown-menu';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';
import { Label } from '@shared/components/ui/label';
import { Switch } from '@shared/components/ui/switch';
import { Tabs, TabsList, TabsTrigger } from '@shared/components/ui/tabs';
import { cn } from '@shared/lib/utils';

import ConfirmAction from '../../Components/ConfirmAction';
import FormRenderer, {
    slotOf,
    type FieldType,
    type FieldValue,
    type FormFieldDef,
} from '../../Components/FormRenderer';
import PpdbPage from '../../Components/PpdbPage';

type Option = { value: string; label: string };
type Rules = Record<string, unknown>;

/** A rule value as input text; anything but a string or number reads as empty. */
function ruleText(value: unknown, fallback = ''): string {
    return typeof value === 'string' || typeof value === 'number'
        ? String(value)
        : fallback;
}

/** A field as the builder holds it: what the page sent, plus what only the builder needs. */
interface Item extends FormFieldDef {
    uid: string;
    locked: boolean;
    hasAnswers: boolean;
}

interface BuilderProps {
    periods: { id: number; name: string; statusLabel: string }[];
    selected: { id: number; name: string; editable: boolean } | null;
    fields: (FormFieldDef & { locked: boolean; hasAnswers: boolean })[];
    paths: Option[];
    types: {
        value: string;
        label: string;
        hasOptions: boolean;
        takesAnswer: boolean;
    }[];
    limits: {
        fields: number;
        options: number;
        fileKb: { min: number; max: number; default: number };
        formats: string[];
    };
}

const FORMAT_LABELS: Record<string, string> = {
    free: 'Teks bebas',
    digits: 'Angka saja',
    email: 'Alamat email',
    phone: 'Nomor telepon',
};

const BUILTIN_HINT: Record<string, string> = {
    path_id: 'Dipakai seleksi: selalu diminta dan wajib.',
    name: 'Dipakai seleksi dan daftar ulang: selalu diminta dan wajib.',
    gender: 'Dipakai daftar ulang: selalu diminta dan wajib.',
};

let newCounter = 0;

function defaultRules(type: FieldType, fileKb: number): Rules {
    switch (type) {
        case 'text':
            return { max_length: null, format: 'free' };
        case 'paragraph':
            return { max_length: null };
        case 'number':
            return { min: null, max: null };
        case 'date':
            return { allow_future: true };
        case 'file':
            return { max_size_kb: fileKb, kinds: ['pdf', 'image'] };
        default:
            return {};
    }
}

/** What the server takes for a field: no builder-only keys, and no id for a field not saved yet. */
function toRow(item: Item) {
    return {
        id: typeof item.id === 'number' ? item.id : null,
        type: item.type,
        label: item.label,
        help: item.help ?? '',
        required: item.required,
        archived: item.archived,
        options: item.options,
        rules: item.rules,
    };
}

function toItem(field: BuilderProps['fields'][number]): Item {
    return { ...field, uid: `f${field.id}` };
}

/** The numeric box of a rule: empty means "no limit". */
function numberOrNull(text: string): number | null {
    return text.trim() === '' ? null : Number(text);
}

/** The editor of one field: label, help, required, options and basic validation. */
function FieldEditor({
    item,
    index,
    types,
    limits,
    errors,
    onChange,
}: {
    item: Item;
    index: number;
    types: BuilderProps['types'];
    limits: BuilderProps['limits'];
    errors: Partial<Record<string, string>>;
    onChange: (patch: Partial<Item>) => void;
}) {
    const err = (suffix: string) => errors[`fields.${index}.${suffix}`];
    const meta = types.find((type) => type.value === item.type);
    const rules = item.rules;
    const setRule = (key: string, value: unknown) =>
        onChange({ rules: { ...rules, [key]: value } });
    const builtin = item.type === 'builtin';
    const idBase = `editor-${item.uid}`;

    function changeType(next: string) {
        const nextMeta = types.find((type) => type.value === next);

        onChange({
            type: next as FieldType,
            rules: defaultRules(next as FieldType, limits.fileKb.default),
            options:
                nextMeta?.hasOptions === true
                    ? item.options.length > 0
                        ? item.options
                        : ['Opsi 1']
                    : [],
            required: nextMeta?.takesAnswer === false ? false : item.required,
        });
    }

    return (
        <div className="flex flex-col gap-4 border-t pt-4">
            {builtin ? (
                <p className="text-sm text-muted-foreground">
                    Isian bawaan ({item.label}).{' '}
                    {item.locked
                        ? (BUILTIN_HINT[item.key ?? ''] ?? '')
                        : 'Bisa dibuat opsional atau diarsipkan.'}
                </p>
            ) : (
                <>
                    <Field data-invalid={err('type') !== undefined}>
                        <FieldLabel>Tipe</FieldLabel>
                        <OptionSelect
                            label={`Tipe kolom ${item.label}`}
                            value={item.type}
                            onChange={changeType}
                            options={types.map((type) => ({
                                value: type.value,
                                label: type.label,
                            }))}
                        />
                        {item.hasAnswers && (
                            <FieldDescription>
                                Sudah ada jawaban, jadi tipe tidak bisa diubah.
                            </FieldDescription>
                        )}
                        {err('type') !== undefined && (
                            <FieldError>{err('type')}</FieldError>
                        )}
                    </Field>
                    <Field data-invalid={err('label') !== undefined}>
                        <FieldLabel htmlFor={`${idBase}-label`}>
                            {item.type === 'section'
                                ? 'Judul bagian'
                                : 'Label pertanyaan'}
                        </FieldLabel>
                        <Input
                            id={`${idBase}-label`}
                            value={item.label}
                            maxLength={150}
                            onChange={(event) =>
                                onChange({ label: event.target.value })
                            }
                            aria-invalid={err('label') !== undefined}
                        />
                        {err('label') !== undefined && (
                            <FieldError>{err('label')}</FieldError>
                        )}
                    </Field>
                </>
            )}

            <Field data-invalid={err('help') !== undefined}>
                <FieldLabel htmlFor={`${idBase}-help`}>
                    Teks bantuan
                    <span className="font-normal text-muted-foreground">
                        {' '}
                        (opsional)
                    </span>
                </FieldLabel>
                <Input
                    id={`${idBase}-help`}
                    value={item.help ?? ''}
                    maxLength={300}
                    onChange={(event) => onChange({ help: event.target.value })}
                    aria-invalid={err('help') !== undefined}
                />
                {err('help') !== undefined && (
                    <FieldError>{err('help')}</FieldError>
                )}
            </Field>

            {(builtin || meta?.takesAnswer !== false) && (
                <div className="flex items-center gap-3">
                    <Switch
                        id={`${idBase}-required`}
                        checked={item.required}
                        disabled={item.locked}
                        onCheckedChange={(checked) =>
                            onChange({ required: checked })
                        }
                    />
                    <Label htmlFor={`${idBase}-required`}>Wajib diisi</Label>
                </div>
            )}

            {meta?.hasOptions === true && (
                <Field data-invalid={err('options') !== undefined}>
                    <FieldLabel>Opsi jawaban</FieldLabel>
                    <div className="flex flex-col gap-2">
                        {item.options.map((option, optionIndex) => (
                            <div key={optionIndex} className="flex gap-2">
                                <Input
                                    aria-label={`Opsi ${optionIndex + 1} ${item.label}`}
                                    value={option}
                                    maxLength={100}
                                    onChange={(event) =>
                                        onChange({
                                            options: item.options.map(
                                                (current, position) =>
                                                    position === optionIndex
                                                        ? event.target.value
                                                        : current,
                                            ),
                                        })
                                    }
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Hapus opsi ${optionIndex + 1}`}
                                    disabled={item.options.length <= 1}
                                    onClick={() =>
                                        onChange({
                                            options: item.options.filter(
                                                (_, position) =>
                                                    position !== optionIndex,
                                            ),
                                        })
                                    }
                                >
                                    <XIcon />
                                </Button>
                            </div>
                        ))}
                        <div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={item.options.length >= limits.options}
                                onClick={() =>
                                    onChange({
                                        options: [
                                            ...item.options,
                                            `Opsi ${item.options.length + 1}`,
                                        ],
                                    })
                                }
                            >
                                <PlusIcon />
                                Tambah opsi
                            </Button>
                        </div>
                    </div>
                    {err('options') !== undefined && (
                        <FieldError>{err('options')}</FieldError>
                    )}
                </Field>
            )}

            {item.type === 'text' && (
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field data-invalid={err('rules.format') !== undefined}>
                        <FieldLabel>Format jawaban</FieldLabel>
                        <OptionSelect
                            label={`Format ${item.label}`}
                            value={ruleText(rules.format, 'free')}
                            onChange={(value) => setRule('format', value)}
                            options={limits.formats.map((format) => ({
                                value: format,
                                label: FORMAT_LABELS[format] ?? format,
                            }))}
                        />
                        {err('rules.format') !== undefined && (
                            <FieldError>{err('rules.format')}</FieldError>
                        )}
                    </Field>
                    <Field data-invalid={err('rules.max_length') !== undefined}>
                        <FieldLabel htmlFor={`${idBase}-maxlen`}>
                            Panjang maksimal
                        </FieldLabel>
                        <Input
                            id={`${idBase}-maxlen`}
                            type="number"
                            min={1}
                            max={500}
                            value={ruleText(rules.max_length)}
                            onChange={(event) =>
                                setRule(
                                    'max_length',
                                    numberOrNull(event.target.value),
                                )
                            }
                        />
                        {err('rules.max_length') !== undefined && (
                            <FieldError>{err('rules.max_length')}</FieldError>
                        )}
                    </Field>
                </div>
            )}

            {item.type === 'paragraph' && (
                <Field data-invalid={err('rules.max_length') !== undefined}>
                    <FieldLabel htmlFor={`${idBase}-maxlen`}>
                        Panjang maksimal
                    </FieldLabel>
                    <Input
                        id={`${idBase}-maxlen`}
                        type="number"
                        min={1}
                        max={2000}
                        value={ruleText(rules.max_length)}
                        onChange={(event) =>
                            setRule(
                                'max_length',
                                numberOrNull(event.target.value),
                            )
                        }
                    />
                    {err('rules.max_length') !== undefined && (
                        <FieldError>{err('rules.max_length')}</FieldError>
                    )}
                </Field>
            )}

            {item.type === 'number' && (
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field data-invalid={err('rules.min') !== undefined}>
                        <FieldLabel htmlFor={`${idBase}-min`}>
                            Nilai minimal
                        </FieldLabel>
                        <Input
                            id={`${idBase}-min`}
                            type="number"
                            step="any"
                            value={ruleText(rules.min)}
                            onChange={(event) =>
                                setRule('min', numberOrNull(event.target.value))
                            }
                        />
                        {err('rules.min') !== undefined && (
                            <FieldError>{err('rules.min')}</FieldError>
                        )}
                    </Field>
                    <Field data-invalid={err('rules.max') !== undefined}>
                        <FieldLabel htmlFor={`${idBase}-max`}>
                            Nilai maksimal
                        </FieldLabel>
                        <Input
                            id={`${idBase}-max`}
                            type="number"
                            step="any"
                            value={ruleText(rules.max)}
                            onChange={(event) =>
                                setRule('max', numberOrNull(event.target.value))
                            }
                        />
                        {err('rules.max') !== undefined && (
                            <FieldError>{err('rules.max')}</FieldError>
                        )}
                    </Field>
                </div>
            )}

            {item.type === 'date' && (
                <div className="flex items-center gap-3">
                    <Switch
                        id={`${idBase}-future`}
                        checked={rules.allow_future !== false}
                        onCheckedChange={(checked) =>
                            setRule('allow_future', checked)
                        }
                    />
                    <Label htmlFor={`${idBase}-future`}>
                        Boleh tanggal di masa depan
                    </Label>
                </div>
            )}

            {item.type === 'file' && (
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        data-invalid={err('rules.max_size_kb') !== undefined}
                    >
                        <FieldLabel htmlFor={`${idBase}-size`}>
                            Ukuran maksimal (KB)
                        </FieldLabel>
                        <Input
                            id={`${idBase}-size`}
                            type="number"
                            min={limits.fileKb.min}
                            max={limits.fileKb.max}
                            value={ruleText(
                                rules.max_size_kb,
                                String(limits.fileKb.default),
                            )}
                            onChange={(event) =>
                                setRule(
                                    'max_size_kb',
                                    numberOrNull(event.target.value),
                                )
                            }
                        />
                        <FieldDescription>
                            Antara {limits.fileKb.min} dan {limits.fileKb.max}{' '}
                            KB.
                        </FieldDescription>
                        {err('rules.max_size_kb') !== undefined && (
                            <FieldError>{err('rules.max_size_kb')}</FieldError>
                        )}
                    </Field>
                    <Field data-invalid={err('rules.kinds') !== undefined}>
                        <FieldLabel>Jenis berkas</FieldLabel>
                        {[
                            { value: 'pdf', label: 'PDF' },
                            { value: 'image', label: 'Gambar (JPG, PNG)' },
                        ].map((kind) => {
                            const kinds = Array.isArray(rules.kinds)
                                ? (rules.kinds as string[])
                                : [];

                            return (
                                <div
                                    key={kind.value}
                                    className="flex items-center gap-2"
                                >
                                    <Checkbox
                                        id={`${idBase}-kind-${kind.value}`}
                                        checked={kinds.includes(kind.value)}
                                        onCheckedChange={(checked) =>
                                            setRule(
                                                'kinds',
                                                checked === true
                                                    ? [...kinds, kind.value]
                                                    : kinds.filter(
                                                          (current) =>
                                                              current !==
                                                              kind.value,
                                                      ),
                                            )
                                        }
                                    />
                                    <Label
                                        htmlFor={`${idBase}-kind-${kind.value}`}
                                        className="font-normal"
                                    >
                                        {kind.label}
                                    </Label>
                                </div>
                            );
                        })}
                        {err('rules.kinds') !== undefined && (
                            <FieldError>{err('rules.kinds')}</FieldError>
                        )}
                    </Field>
                </div>
            )}
        </div>
    );
}

/** One card of the list: drag handle, summary, move and archive buttons, and the editor when opened. */
function FieldCard({
    item,
    index,
    count,
    open,
    types,
    limits,
    errors,
    onToggle,
    onChange,
    onMove,
    onRemove,
}: {
    item: Item;
    index: number;
    count: number;
    open: boolean;
    types: BuilderProps['types'];
    limits: BuilderProps['limits'];
    errors: Partial<Record<string, string>>;
    onToggle: () => void;
    onChange: (patch: Partial<Item>) => void;
    onMove: (delta: number) => void;
    onRemove: () => void;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: item.uid });
    const typeLabel =
        item.type === 'builtin'
            ? 'Bawaan'
            : (types.find((type) => type.value === item.type)?.label ??
              item.type);
    const hasError = Object.keys(errors).some((key) =>
        key.startsWith(`fields.${index}.`),
    );

    return (
        <div
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={cn(
                'rounded-lg border bg-card p-3',
                isDragging && 'z-10 shadow-lg',
                item.archived && 'opacity-60',
                hasError && 'border-destructive',
            )}
            data-testid={`field-card-${index}`}
        >
            <div className="flex items-center gap-2">
                <button
                    type="button"
                    className="cursor-grab touch-none text-muted-foreground active:cursor-grabbing"
                    aria-label={`Seret ${item.label}`}
                    {...attributes}
                    {...listeners}
                >
                    <GripVerticalIcon className="size-5" />
                </button>
                <button
                    type="button"
                    className="flex min-w-0 flex-1 flex-col items-start text-left"
                    onClick={onToggle}
                    aria-expanded={open}
                >
                    <span className="max-w-full truncate text-sm font-medium">
                        {item.label || '(tanpa label)'}
                    </span>
                    <span className="flex flex-wrap items-center gap-1 text-xs text-muted-foreground">
                        {typeLabel}
                        {item.type !== 'section' && item.required && (
                            <Badge variant="secondary">Wajib</Badge>
                        )}
                        {item.archived && (
                            <Badge variant="outline">Diarsipkan</Badge>
                        )}
                        {typeof item.id !== 'number' && (
                            <Badge variant="outline">Baru</Badge>
                        )}
                    </span>
                </button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={`Naikkan ${item.label}`}
                    disabled={index === 0}
                    onClick={() => onMove(-1)}
                >
                    <ArrowUpIcon />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={`Turunkan ${item.label}`}
                    disabled={index === count - 1}
                    onClick={() => onMove(1)}
                >
                    <ArrowDownIcon />
                </Button>
                {!item.locked && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={`${item.archived ? 'Pulihkan' : 'Arsipkan'} ${item.label}`}
                        onClick={() => onChange({ archived: !item.archived })}
                    >
                        {item.archived ? (
                            <ArchiveRestoreIcon />
                        ) : (
                            <ArchiveIcon />
                        )}
                    </Button>
                )}
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={
                        open ? `Tutup ${item.label}` : `Ubah ${item.label}`
                    }
                    onClick={onToggle}
                >
                    {open ? <ChevronUpIcon /> : <ChevronDownIcon />}
                </Button>
            </div>

            {open && (
                <div className="mt-3">
                    <FieldEditor
                        item={item}
                        index={index}
                        types={types}
                        limits={limits}
                        errors={errors}
                        onChange={onChange}
                    />
                    {item.type !== 'builtin' && (
                        <div className="mt-4 flex flex-wrap items-center gap-3">
                            {item.hasAnswers ? (
                                <p className="text-sm text-muted-foreground">
                                    Kolom ini sudah punya jawaban: arsipkan bila
                                    tidak dipakai lagi.
                                </p>
                            ) : typeof item.id !== 'number' ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={onRemove}
                                >
                                    Buang kolom
                                </Button>
                            ) : (
                                <ConfirmAction
                                    trigger={
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                        >
                                            Hapus kolom
                                        </Button>
                                    }
                                    title={`Hapus kolom ${item.label}?`}
                                    description="Kolom dihapus dari formulir. Perubahan lain yang belum disimpan di halaman ini ikut hilang."
                                    confirmLabel="Hapus kolom"
                                    onConfirm={onRemove}
                                />
                            )}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

/** The builder itself: the fields in order on the left, the form as an applicant sees it on the right. */
function Builder({
    selected,
    fields,
    paths,
    types,
    limits,
}: Omit<BuilderProps, 'periods'> & {
    selected: NonNullable<BuilderProps['selected']>;
}) {
    const form = useForm<{ fields: Item[] }>({ fields: fields.map(toItem) });
    const [open, setOpen] = useState<string | null>(null);
    const [tab, setTab] = useState('susun');
    const [preview, setPreview] = useState<Record<string, FieldValue>>({});
    const errors: Partial<Record<string, string>> = form.errors;
    const items = form.data.fields;
    const editable = selected.editable;

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    useEffect(() => {
        if (!form.isDirty) {
            return;
        }

        const warn = (event: BeforeUnloadEvent) => event.preventDefault();

        window.addEventListener('beforeunload', warn);

        return () => window.removeEventListener('beforeunload', warn);
    }, [form.isDirty]);

    function setItems(next: Item[]) {
        form.setData('fields', next);
    }

    function patch(uid: string, changes: Partial<Item>) {
        setItems(
            items.map((item) =>
                item.uid === uid ? { ...item, ...changes } : item,
            ),
        );
    }

    function move(from: number, to: number) {
        if (to < 0 || to >= items.length) {
            return;
        }

        setItems(arrayMove(items, from, to));
    }

    function onDragEnd(event: DragEndEvent) {
        if (event.over === null || event.active.id === event.over.id) {
            return;
        }

        move(
            items.findIndex((item) => item.uid === event.active.id),
            items.findIndex((item) => item.uid === event.over?.id),
        );
    }

    function add(type: string) {
        const meta = types.find((candidate) => candidate.value === type);
        const uid = `new-${++newCounter}`;
        const item: Item = {
            uid,
            id: uid,
            key: null,
            type: type as FieldType,
            label: type === 'section' ? 'Bagian baru' : 'Pertanyaan baru',
            help: null,
            required: false,
            archived: false,
            locked: false,
            hasAnswers: false,
            options: meta?.hasOptions === true ? ['Opsi 1', 'Opsi 2'] : [],
            rules: defaultRules(type as FieldType, limits.fileKb.default),
        };

        setItems([...items, item]);
        setOpen(uid);
    }

    function remove(item: Item) {
        if (typeof item.id === 'number') {
            router.delete(destroy.url({ field: item.id }), {
                preserveScroll: true,
            });

            return;
        }

        setItems(items.filter((candidate) => candidate.uid !== item.uid));
    }

    function save() {
        form.transform((data) => ({ fields: data.fields.map(toRow) }));
        form.put(update.url({ period: selected.id }), {
            preserveScroll: true,
            onSuccess: () => setOpen(null),
        });
    }

    const refusal = errors.form;

    return (
        <div className="flex flex-col gap-6">
            {!editable && (
                <Alert>
                    <AlertDescription>
                        Periode ini sudah ditutup, jadi formulirnya hanya bisa
                        dilihat.
                    </AlertDescription>
                </Alert>
            )}
            {refusal !== undefined && (
                <Alert variant="destructive">
                    <AlertDescription>{refusal}</AlertDescription>
                </Alert>
            )}

            <Tabs value={tab} onValueChange={setTab} className="lg:hidden">
                <TabsList>
                    <TabsTrigger value="susun">Susun</TabsTrigger>
                    <TabsTrigger value="pratinjau">Pratinjau</TabsTrigger>
                </TabsList>
            </Tabs>

            <div className="grid items-start gap-6 lg:grid-cols-2">
                <div
                    className={cn(
                        'flex flex-col gap-4',
                        tab !== 'susun' && 'hidden lg:flex',
                    )}
                >
                    <Panel
                        title="Kolom formulir"
                        actions={
                            editable ? (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            disabled={
                                                items.length >= limits.fields
                                            }
                                        >
                                            <PlusIcon />
                                            Tambah kolom
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        {types.map((type) => (
                                            <DropdownMenuItem
                                                key={type.value}
                                                onSelect={() => add(type.value)}
                                            >
                                                {type.label}
                                            </DropdownMenuItem>
                                        ))}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            ) : undefined
                        }
                    >
                        <fieldset disabled={!editable} className="contents">
                            <DndContext
                                sensors={sensors}
                                collisionDetection={closestCenter}
                                onDragEnd={onDragEnd}
                            >
                                <SortableContext
                                    items={items.map((item) => item.uid)}
                                    strategy={verticalListSortingStrategy}
                                >
                                    <div className="flex flex-col gap-2">
                                        {items.map((item, index) => (
                                            <FieldCard
                                                key={item.uid}
                                                item={item}
                                                index={index}
                                                count={items.length}
                                                open={open === item.uid}
                                                types={types}
                                                limits={limits}
                                                errors={errors}
                                                onToggle={() =>
                                                    setOpen(
                                                        open === item.uid
                                                            ? null
                                                            : item.uid,
                                                    )
                                                }
                                                onChange={(changes) =>
                                                    patch(item.uid, changes)
                                                }
                                                onMove={(delta) =>
                                                    move(index, index + delta)
                                                }
                                                onRemove={() => remove(item)}
                                            />
                                        ))}
                                    </div>
                                </SortableContext>
                            </DndContext>
                        </fieldset>
                    </Panel>

                    {editable && (
                        <div className="flex items-center gap-3">
                            <Button
                                type="button"
                                onClick={save}
                                disabled={form.processing || !form.isDirty}
                            >
                                Simpan formulir
                            </Button>
                            {form.isDirty && (
                                <span className="text-sm text-muted-foreground">
                                    Ada perubahan yang belum disimpan.
                                </span>
                            )}
                        </div>
                    )}
                </div>

                <div className={cn(tab !== 'pratinjau' && 'hidden lg:block')}>
                    <Panel title="Pratinjau">
                        <p className="mb-4 text-sm text-muted-foreground">
                            Seperti inilah formulir tampil bagi calon siswa.
                            Pratinjau tidak mengirim data.
                        </p>
                        <FormRenderer
                            fields={items}
                            paths={paths}
                            getValue={(field) =>
                                preview[slotOf(field)] ??
                                (field.type === 'checkboxes' ? [] : '')
                            }
                            setValue={(field, value) =>
                                setPreview((current) => ({
                                    ...current,
                                    [slotOf(field)]: value,
                                }))
                            }
                            errorOf={() => undefined}
                        />
                    </Panel>
                </div>
            </div>
        </div>
    );
}

/** Formulir: the school builds the registration form of a period. */
export default function FormBuilder({
    periods,
    selected,
    fields,
    paths,
    types,
    limits,
}: BuilderProps) {
    return (
        <PpdbPage
            title="Formulir pendaftaran"
            description="Susun isian formulir yang diisi calon siswa: tambah pertanyaan, atur urutan, dan lihat hasilnya langsung."
            width="max-w-6xl"
        >
            {selected === null ? (
                <EmptyState>
                    Buat periode PPDB di Pengaturan lebih dulu untuk menyusun
                    formulirnya.
                </EmptyState>
            ) : (
                <div className="flex flex-col gap-6">
                    {periods.length > 1 && (
                        <Field className="max-w-sm">
                            <FieldLabel>Periode</FieldLabel>
                            <OptionSelect
                                label="Periode yang disusun"
                                value={String(selected.id)}
                                onChange={(value) =>
                                    router.get(
                                        formRoute.url({
                                            query: { periode: value },
                                        }),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                                options={periods.map((period) => ({
                                    value: String(period.id),
                                    label: `${period.name} · ${period.statusLabel}`,
                                }))}
                            />
                        </Field>
                    )}
                    <Builder
                        key={`builder-${selected.id}-${JSON.stringify(fields)}`}
                        selected={selected}
                        fields={fields}
                        paths={paths}
                        types={types}
                        limits={limits}
                    />
                </div>
            )}
        </PpdbPage>
    );
}
