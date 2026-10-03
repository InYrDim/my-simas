import { router, useForm } from '@inertiajs/react';

import {
    index,
    update,
} from '@/actions/Modules/Core/App/Http/Controllers/TeachingAssignmentController';
import {
    DataTable,
    EmptyState,
    OptionSelect,
} from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Button } from '@shared/components/ui/button';
import { Input } from '@shared/components/ui/input';
import { TableCell, TableRow } from '@shared/components/ui/table';

import MasterPage from '../../../../Components/MasterPage';
import type {
    Assignment,
    ClassGroup,
    SchoolSummary,
    Subject,
    Teacher,
} from '../../../../types/master';

const NONE = 'none';

interface Row {
    teacher: string;
    hours: string;
}

/**
 * Assignments of one class: a teacher and the hours per week for each
 * subject. A subject left on "Belum ditentukan" has no teacher.
 */
function ClassAssignments({
    classGroup,
    subjects,
    teachers,
    assignments,
}: {
    classGroup: ClassGroup;
    subjects: Subject[];
    teachers: Teacher[];
    assignments: Assignment[];
}) {
    const form = useForm<{ rows: Record<number, Row> }>({
        rows: Object.fromEntries(
            subjects.map((subject) => {
                const found = assignments.find(
                    (row) => row.subjectId === subject.id,
                );

                return [
                    subject.id,
                    {
                        teacher: String(found?.teacherId ?? NONE),
                        hours: String(found?.hours ?? 2),
                    },
                ];
            }),
        ),
    });

    form.transform((data) => ({
        assignments: Object.entries(data.rows)
            .filter(([, row]) => row.teacher !== NONE)
            .map(([subjectId, row]) => ({
                subject_id: Number(subjectId),
                teacher_id: Number(row.teacher),
                hours: Number(row.hours),
            })),
    }));

    const options = [
        { value: NONE, label: 'Belum ditentukan' },
        ...teachers.map((teacher) => ({
            value: String(teacher.id),
            label: teacher.name,
        })),
    ];
    const firstError = Object.values(form.errors)[0];

    const change = (subjectId: number, patch: Partial<Row>) =>
        form.setData('rows', {
            ...form.data.rows,
            [subjectId]: { ...form.data.rows[subjectId], ...patch },
        });

    return (
        <>
            {firstError !== undefined && (
                <Alert variant="destructive" className="mb-6">
                    <AlertDescription>{firstError}</AlertDescription>
                </Alert>
            )}

            <DataTable head={['Mata pelajaran', 'Guru pengampu', 'JP/minggu']}>
                {subjects.map((subject) => {
                    const row = form.data.rows[subject.id];

                    return (
                        <TableRow key={subject.id}>
                            <TableCell className="font-medium">
                                {subject.name}
                            </TableCell>
                            <TableCell className="w-80">
                                <OptionSelect
                                    label={`Guru ${subject.name}`}
                                    value={row.teacher}
                                    onChange={(value) =>
                                        change(subject.id, { teacher: value })
                                    }
                                    options={options}
                                />
                            </TableCell>
                            <TableCell className="w-28">
                                <Input
                                    type="number"
                                    min={1}
                                    max={20}
                                    aria-label={`JP per minggu ${subject.name}`}
                                    value={row.hours}
                                    disabled={row.teacher === NONE}
                                    onChange={(event) =>
                                        change(subject.id, {
                                            hours: event.target.value,
                                        })
                                    }
                                />
                            </TableCell>
                        </TableRow>
                    );
                })}
            </DataTable>

            <div className="mt-4 flex justify-end">
                <Button
                    disabled={!form.isDirty || form.processing}
                    onClick={() =>
                        form.put(update.url(classGroup.id), {
                            preserveScroll: true,
                            onSuccess: () => form.setDefaults(),
                        })
                    }
                >
                    Simpan pengampu {classGroup.name}
                </Button>
            </div>
        </>
    );
}

/**
 * Pengampu Mapel: assign a teacher to each subject of one class of the
 * active academic year.
 */
export default function AssignmentsIndex({
    school,
    classes,
    classId,
    subjects,
    teachers,
    assignments,
}: {
    school: SchoolSummary;
    classes: ClassGroup[];
    classId: number | null;
    subjects: Subject[];
    teachers: Teacher[];
    assignments: Assignment[];
}) {
    const classGroup = classes.find((group) => group.id === classId);

    return (
        <MasterPage
            school={school}
            title="Pengampu Mapel"
            description="Tentukan guru pengampu tiap mata pelajaran di sebuah kelas."
            width="max-w-5xl"
            mock={false}
        >
            {classGroup === undefined ? (
                <EmptyState>
                    Belum ada kelas pada tahun ajaran aktif. Aktifkan tahun
                    ajaran dan buat kelasnya di Master Data.
                </EmptyState>
            ) : subjects.length === 0 ? (
                <EmptyState>
                    Belum ada mata pelajaran. Tambahkan di Master Data › Mata
                    Pelajaran.
                </EmptyState>
            ) : (
                <>
                    <div className="mb-4 max-w-xs">
                        <OptionSelect
                            label="Kelas"
                            value={String(classGroup.id)}
                            onChange={(value) =>
                                router.get(
                                    index.url({ query: { kelas: value } }),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                            options={classes.map((group) => ({
                                value: String(group.id),
                                label: `Kelas ${group.name}`,
                            }))}
                        />
                    </div>

                    <ClassAssignments
                        key={classGroup.id}
                        classGroup={classGroup}
                        subjects={subjects}
                        teachers={teachers}
                        assignments={assignments}
                    />
                </>
            )}
        </MasterPage>
    );
}
