import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

import {
    index,
    store,
} from '@/actions/Modules/Core/App/Http/Controllers/PlacementController';
import {
    DataTable,
    EmptyState,
    OptionSelect,
    Panel,
} from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Button } from '@shared/components/ui/button';
import { Checkbox } from '@shared/components/ui/checkbox';
import { TableCell, TableRow } from '@shared/components/ui/table';

import MasterPage from '../../../../Components/MasterPage';
import type {
    AcademicYear,
    ClassGroup,
    SchoolSummary,
    Student,
} from '../../../../types/master';

type Action = 'promote' | 'move' | 'graduate';

const actions: { value: Action; label: string }[] = [
    { value: 'promote', label: 'Naikkan kelas' },
    { value: 'move', label: 'Pindahkan rombel' },
    { value: 'graduate', label: 'Luluskan' },
];

const verbs: Record<Action, string> = {
    promote: 'dinaikkan',
    move: 'dipindahkan',
    graduate: 'diluluskan',
};

/**
 * The part of the page that depends on the source class (keyed by it, so
 * choosing another class starts with a clean selection).
 */
function PlacementForm({
    source,
    sourceYear,
    years,
    classes,
    students,
}: {
    source: ClassGroup;
    sourceYear: AcademicYear;
    years: AcademicYear[];
    classes: ClassGroup[];
    students: Student[];
}) {
    const laterYears = years
        .filter((year) => year.start > sourceYear.start)
        .sort((a, b) => a.start.localeCompare(b.start));

    const [action, setAction] = useState<Action>('promote');
    const [toYear, setToYear] = useState(String(laterYears[0]?.id ?? ''));
    const [targetClass, setTargetClass] = useState('');
    const [selected, setSelected] = useState<number[]>([]);
    const form = useForm({});

    const targets =
        action === 'move'
            ? classes.filter(
                  (item) =>
                      item.yearId === source.yearId && item.id !== source.id,
              )
            : action === 'promote'
              ? classes.filter((item) => String(item.yearId) === toYear)
              : [];
    const target =
        targets.find((item) => String(item.id) === targetClass) ?? targets[0];

    const toggle = (id: number, checked: boolean) =>
        setSelected((current) =>
            checked ? [...current, id] : current.filter((item) => item !== id),
        );
    const canSubmit =
        selected.length > 0 && (action === 'graduate' || target !== undefined);

    form.transform(() => ({
        action,
        source_class_id: source.id,
        target_class_id: action === 'graduate' ? null : target?.id,
        student_ids: selected,
    }));

    const submit = () =>
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => setSelected([]),
        });

    const firstError = Object.values(form.errors)[0];

    return (
        <div className="grid gap-6 lg:grid-cols-3">
            <Panel title="Tindakan" className="lg:col-span-1">
                <div className="flex flex-col gap-4">
                    <OptionSelect
                        label="Tindakan"
                        value={action}
                        onChange={(value) => setAction(value as Action)}
                        options={actions}
                    />
                    {action === 'promote' && (
                        <OptionSelect
                            label="Ke tahun ajaran"
                            value={toYear}
                            onChange={setToYear}
                            options={laterYears.map((year) => ({
                                value: String(year.id),
                                label: year.name,
                            }))}
                            placeholder="Belum ada tahun ajaran lebih baru"
                        />
                    )}
                    {action !== 'graduate' && (
                        <OptionSelect
                            label="Rombel tujuan"
                            value={
                                target === undefined ? '' : String(target.id)
                            }
                            onChange={setTargetClass}
                            options={targets.map((item) => ({
                                value: String(item.id),
                                label: item.name,
                            }))}
                            placeholder="Belum ada rombel tujuan"
                        />
                    )}
                </div>
            </Panel>

            <div className="flex flex-col gap-6 lg:col-span-2">
                <Panel title={`Siswa ${source.name}`}>
                    {students.length === 0 ? (
                        <EmptyState>
                            Belum ada siswa aktif di rombel ini.
                        </EmptyState>
                    ) : (
                        <>
                            <div className="mb-3 flex justify-end">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        setSelected(
                                            selected.length === students.length
                                                ? []
                                                : students.map(
                                                      (student) => student.id,
                                                  ),
                                        )
                                    }
                                >
                                    {selected.length === students.length
                                        ? 'Kosongkan pilihan'
                                        : 'Pilih semua'}
                                </Button>
                            </div>
                            <DataTable head={['', 'Nama', 'NIS']}>
                                {students.map((student) => (
                                    <TableRow key={student.id}>
                                        <TableCell className="w-10">
                                            <Checkbox
                                                aria-label={`Pilih ${student.name}`}
                                                checked={selected.includes(
                                                    student.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    toggle(
                                                        student.id,
                                                        checked === true,
                                                    )
                                                }
                                            />
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {student.name}
                                        </TableCell>
                                        <TableCell>{student.nis}</TableCell>
                                    </TableRow>
                                ))}
                            </DataTable>
                        </>
                    )}
                </Panel>

                <Panel title="Pratinjau perubahan">
                    {firstError !== undefined && (
                        <Alert variant="destructive" className="mb-4">
                            <AlertDescription>{firstError}</AlertDescription>
                        </Alert>
                    )}
                    <p className="text-sm">
                        {selected.length === 0
                            ? 'Pilih siswa untuk melihat pratinjau.'
                            : action === 'graduate'
                              ? `${selected.length} siswa dari ${source.name} akan ${verbs[action]}.`
                              : `${selected.length} siswa dari ${source.name} akan ${verbs[action]} ke ${target?.name ?? '—'}.`}
                    </p>
                    <div className="mt-4 flex justify-end">
                        <Button
                            disabled={!canSubmit || form.processing}
                            onClick={submit}
                        >
                            Konfirmasi penempatan
                        </Button>
                    </div>
                </Panel>
            </div>
        </div>
    );
}

/**
 * Students who have no class yet (new students, such as those who
 * re-registered through admissions): the only thing to do with them is to
 * give them a class of a year that is running or still to come.
 */
function UnplacedForm({
    years,
    classes,
    students,
}: {
    years: AcademicYear[];
    classes: ClassGroup[];
    students: Student[];
}) {
    const open = years
        .filter((year) => year.status !== 'archived')
        .sort(
            (a, b) =>
                Number(b.status === 'active') - Number(a.status === 'active') ||
                a.start.localeCompare(b.start),
        );
    const targets = open.flatMap((year) =>
        classes
            .filter((item) => item.yearId === year.id)
            .map((item) => ({
                value: String(item.id),
                label: `${item.name} · ${year.name}`,
                name: item.name,
            })),
    );

    const [targetClass, setTargetClass] = useState('');
    const [selected, setSelected] = useState<number[]>([]);
    const form = useForm({});

    const target =
        targets.find((item) => item.value === targetClass) ?? targets[0];
    const toggle = (id: number, checked: boolean) =>
        setSelected((current) =>
            checked ? [...current, id] : current.filter((item) => item !== id),
        );
    const canSubmit = selected.length > 0 && target !== undefined;

    form.transform(() => ({
        action: 'assign',
        target_class_id: target === undefined ? null : Number(target.value),
        student_ids: selected,
    }));

    const submit = () =>
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => setSelected([]),
        });

    const firstError = Object.values(form.errors)[0];

    return (
        <div className="grid gap-6 lg:grid-cols-3">
            <Panel title="Tujuan" className="lg:col-span-1">
                <div className="flex flex-col gap-4">
                    <OptionSelect
                        label="Rombel tujuan"
                        value={target?.value ?? ''}
                        onChange={setTargetClass}
                        options={targets}
                        placeholder="Belum ada rombel tujuan"
                    />
                    {targets.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Belum ada rombel pada tahun ajaran yang berjalan
                            atau akan datang. Buat kelas di Master Data lebih
                            dulu.
                        </p>
                    )}
                </div>
            </Panel>

            <div className="flex flex-col gap-6 lg:col-span-2">
                <Panel title="Siswa belum ditempatkan">
                    {students.length === 0 ? (
                        <EmptyState>
                            Semua siswa aktif sudah punya kelas.
                        </EmptyState>
                    ) : (
                        <>
                            <div className="mb-3 flex justify-end">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        setSelected(
                                            selected.length === students.length
                                                ? []
                                                : students.map(
                                                      (student) => student.id,
                                                  ),
                                        )
                                    }
                                >
                                    {selected.length === students.length
                                        ? 'Kosongkan pilihan'
                                        : 'Pilih semua'}
                                </Button>
                            </div>
                            <DataTable head={['', 'Nama', 'NIS']}>
                                {students.map((student) => (
                                    <TableRow key={student.id}>
                                        <TableCell className="w-10">
                                            <Checkbox
                                                aria-label={`Pilih ${student.name}`}
                                                checked={selected.includes(
                                                    student.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    toggle(
                                                        student.id,
                                                        checked === true,
                                                    )
                                                }
                                            />
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {student.name}
                                        </TableCell>
                                        <TableCell>{student.nis}</TableCell>
                                    </TableRow>
                                ))}
                            </DataTable>
                        </>
                    )}
                </Panel>

                <Panel title="Pratinjau perubahan">
                    {firstError !== undefined && (
                        <Alert variant="destructive" className="mb-4">
                            <AlertDescription>{firstError}</AlertDescription>
                        </Alert>
                    )}
                    <p className="text-sm">
                        {selected.length === 0
                            ? 'Pilih siswa untuk melihat pratinjau.'
                            : `${selected.length} siswa belum ditempatkan akan ditempatkan ke ${target?.name ?? '—'}.`}
                    </p>
                    <div className="mt-4 flex justify-end">
                        <Button
                            disabled={!canSubmit || form.processing}
                            onClick={submit}
                        >
                            Tempatkan
                        </Button>
                    </div>
                </Panel>
            </div>
        </div>
    );
}

/**
 * Penempatan Siswa: bulk tool to promote, move or graduate the students of
 * one rombel, with a preview of the change before confirming.
 */
export default function PlacementIndex({
    school,
    years,
    classes,
    sourceClassId,
    unplaced,
    unplacedCount,
    students,
}: {
    school: SchoolSummary;
    years: AcademicYear[];
    classes: ClassGroup[];
    sourceClassId: number | null;
    unplaced: boolean;
    unplacedCount: number;
    students: Student[];
}) {
    const source = classes.find((item) => item.id === sourceClassId);
    const sourceYear = years.find((year) => year.id === source?.yearId);
    const yearsWithClasses = years.filter((year) =>
        classes.some((item) => item.yearId === year.id),
    );

    const open = (classId: number | 'belum') =>
        router.get(
            index.url({ query: { kelas: classId } }),
            {},
            { preserveScroll: true },
        );

    return (
        <MasterPage
            school={school}
            title="Penempatan Siswa"
            description="Naikkan, pindahkan, atau luluskan siswa secara massal saat pergantian tahun ajaran."
            mock={false}
        >
            {unplaced ? (
                <>
                    <div className="mb-6 grid gap-3 sm:grid-cols-2">
                        <OptionSelect
                            label="Rombel asal"
                            value="belum"
                            onChange={(value) =>
                                open(
                                    value === 'belum' ? 'belum' : Number(value),
                                )
                            }
                            options={[
                                {
                                    value: 'belum',
                                    label: `Belum ditempatkan (${unplacedCount})`,
                                },
                                ...classes.map((item) => ({
                                    value: String(item.id),
                                    label: item.name,
                                })),
                            ]}
                        />
                    </div>
                    <UnplacedForm
                        years={years}
                        classes={classes}
                        students={students}
                    />
                </>
            ) : source === undefined || sourceYear === undefined ? (
                <EmptyState>
                    Belum ada kelas. Buat kelas di Master Data lebih dulu.
                </EmptyState>
            ) : (
                <>
                    {unplacedCount > 0 && (
                        <Alert className="mb-6">
                            <AlertDescription className="flex flex-wrap items-center justify-between gap-3">
                                <span>
                                    {unplacedCount} siswa aktif belum
                                    ditempatkan di rombel mana pun.
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => open('belum')}
                                >
                                    Tempatkan siswa
                                </Button>
                            </AlertDescription>
                        </Alert>
                    )}
                    <div className="mb-6 grid gap-3 sm:grid-cols-2">
                        <OptionSelect
                            label="Dari tahun ajaran"
                            value={String(sourceYear.id)}
                            onChange={(value) => {
                                const first = classes.find(
                                    (item) => String(item.yearId) === value,
                                );

                                if (first !== undefined) {
                                    open(first.id);
                                }
                            }}
                            options={yearsWithClasses.map((year) => ({
                                value: String(year.id),
                                label: year.name,
                            }))}
                        />
                        <OptionSelect
                            label="Rombel asal"
                            value={String(source.id)}
                            onChange={(value) =>
                                open(
                                    value === 'belum' ? 'belum' : Number(value),
                                )
                            }
                            options={[
                                ...(unplacedCount > 0
                                    ? [
                                          {
                                              value: 'belum',
                                              label: `Belum ditempatkan (${unplacedCount})`,
                                          },
                                      ]
                                    : []),
                                ...classes
                                    .filter(
                                        (item) => item.yearId === sourceYear.id,
                                    )
                                    .map((item) => ({
                                        value: String(item.id),
                                        label: item.name,
                                    })),
                            ]}
                        />
                    </div>

                    <PlacementForm
                        key={source.id}
                        source={source}
                        sourceYear={sourceYear}
                        years={years}
                        classes={classes}
                        students={students}
                    />
                </>
            )}
        </MasterPage>
    );
}
