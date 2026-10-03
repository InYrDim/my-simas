import { Link } from '@inertiajs/react';
import { CheckIcon } from 'lucide-react';

import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';

import { index as academicYears } from '@/actions/Modules/Core/App/Http/Controllers/AcademicYearController';
import { index as classes } from '@/actions/Modules/Core/App/Http/Controllers/ClassGroupController';
import { index as extracurriculars } from '@/actions/Modules/Core/App/Http/Controllers/ExtracurricularController';
import { index as grades } from '@/actions/Modules/Core/App/Http/Controllers/GradeController';
import { index as placement } from '@/actions/Modules/Core/App/Http/Controllers/PlacementController';
import { index as rooms } from '@/actions/Modules/Core/App/Http/Controllers/RoomController';
import { show as schoolProfile } from '@/actions/Modules/Core/App/Http/Controllers/SchoolProfileController';
import { index as students } from '@/actions/Modules/Core/App/Http/Controllers/StudentController';
import { index as subjects } from '@/actions/Modules/Core/App/Http/Controllers/SubjectController';
import { index as teachers } from '@/actions/Modules/Core/App/Http/Controllers/TeacherController';

type StepState = 'done' | 'next' | 'todo' | 'blocked' | 'optional';

export interface SetupStep {
    key: string;
    label: string;
    hint: string | null;
    state: StepState;
    optional: boolean;
}

/** What a new school still has to fill in; null once all required steps are done. */
export interface SetupChecklistData {
    done: number;
    total: number;
    unplaced: number;
    steps: SetupStep[];
}

/**
 * The page a step is done on. Jurusan has no page of its own: it is edited
 * on Tingkat & Jurusan. Siswa goes straight to the placement of those who
 * still have no class once there are such students.
 */
function hrefFor(key: string, unplaced: number): string | null {
    switch (key) {
        case 'profile':
            return schoolProfile.url();
        case 'year':
            return academicYears.url();
        case 'majors':
            return grades.url();
        case 'rooms':
            return rooms.url();
        case 'subjects':
            return subjects.url();
        case 'teachers':
            return teachers.url();
        case 'classes':
            return classes.url();
        case 'students':
            return unplaced > 0
                ? placement.url({ query: { kelas: 'belum' } })
                : students.url();
        case 'extracurriculars':
            return extracurriculars.url();
        default:
            return null;
    }
}

/**
 * "Persiapan sekolah" — the steps a new school fills in, in the order its
 * data depends on itself. Quiet and ruled like the rest of the record;
 * only the step to do next is set apart.
 */
export default function SetupChecklist({
    setup,
}: {
    setup: SetupChecklistData;
}) {
    return (
        <section aria-labelledby="setup-heading" className="mt-10">
            <div className="flex items-baseline justify-between gap-6">
                <h2
                    id="setup-heading"
                    className="text-sm font-semibold text-foreground"
                >
                    Persiapan sekolah
                </h2>
                <p className="text-xs text-muted-foreground">
                    {setup.done} dari {setup.total} selesai
                </p>
            </div>

            <ol className="mt-3 border-t border-border">
                {setup.steps.map((step) => {
                    const href =
                        step.state === 'blocked'
                            ? null
                            : hrefFor(step.key, setup.unplaced);

                    return (
                        <li
                            key={step.key}
                            data-state={step.state}
                            className="flex items-center justify-between gap-6 border-b border-border py-3"
                        >
                            <div className="min-w-0">
                                <p
                                    className={
                                        step.state === 'next'
                                            ? 'text-sm font-semibold text-foreground'
                                            : step.state === 'done'
                                              ? 'text-sm text-muted-foreground'
                                              : 'text-sm text-foreground'
                                    }
                                >
                                    {step.label}
                                    {step.state === 'next' && (
                                        <Badge className="ml-2 align-middle">
                                            Berikutnya
                                        </Badge>
                                    )}
                                    {step.state === 'optional' && (
                                        <Badge
                                            variant="secondary"
                                            className="ml-2 align-middle"
                                        >
                                            Opsional
                                        </Badge>
                                    )}
                                </p>

                                {step.hint !== null && (
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {step.hint}
                                    </p>
                                )}
                            </div>

                            <div className="shrink-0">
                                {step.state === 'done' && (
                                    <>
                                        <CheckIcon
                                            aria-hidden="true"
                                            className="size-4 text-primary"
                                        />
                                        <span className="sr-only">Selesai</span>
                                    </>
                                )}

                                {step.state === 'next' && href !== null && (
                                    <Button asChild size="sm">
                                        <Link href={href}>Kerjakan</Link>
                                    </Button>
                                )}

                                {(step.state === 'todo' ||
                                    step.state === 'optional') &&
                                    href !== null && (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="ghost"
                                        >
                                            <Link href={href}>Buka</Link>
                                        </Button>
                                    )}
                            </div>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}
