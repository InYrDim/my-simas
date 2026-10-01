import { Panel, StatCard } from '@shared/components/page-parts';

import MasterPage from '../../../Components/MasterPage';
import type { SchoolSummary } from '../../../types/master';

interface StatisticsProps {
    school: SchoolSummary;
    totals: { students: number; teachers: number; classes: number; attendance: number };
    byGrade: { name: string; students: number }[];
    gender: { male: number; female: number };
    attendance: { month: string; percent: number }[];
}

/** Statistik: the school in numbers — headcount, spread by grade, attendance trend. */
export default function Statistics({ school, totals, byGrade, gender, attendance }: StatisticsProps) {
    const widest = Math.max(...byGrade.map((row) => row.students), 1);
    const malePercent = Math.round((gender.male / (gender.male + gender.female)) * 100);

    return (
        <MasterPage
            school={school}
            title="Statistik"
            description="Gambaran singkat sekolah pada semester berjalan."
            width="max-w-6xl"
        >
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard label="Siswa aktif" value={totals.students} />
                <StatCard label="Guru & tendik" value={totals.teachers} />
                <StatCard label="Kelas" value={totals.classes} />
                <StatCard label="Rata-rata kehadiran" value={`${totals.attendance}%`} hint="bulan ini" />
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Panel title={`Siswa per ${school.level === 'sd' ? 'kelas' : 'tingkat'}`}>
                    <ul className="flex flex-col gap-3">
                        {byGrade.map((row) => (
                            <li key={row.name}>
                                <div className="flex justify-between text-sm">
                                    <span>{row.name}</span>
                                    <span className="font-medium">{row.students}</span>
                                </div>
                                <div
                                    role="progressbar"
                                    aria-label={`Tingkat ${row.name}`}
                                    aria-valuenow={row.students}
                                    aria-valuemin={0}
                                    aria-valuemax={widest}
                                    className="mt-1.5 h-2 bg-muted"
                                >
                                    <div
                                        className="h-full bg-primary"
                                        style={{ width: `${Math.round((row.students / widest) * 100)}%` }}
                                    />
                                </div>
                            </li>
                        ))}
                    </ul>
                </Panel>

                <Panel title="Jenis kelamin">
                    <div
                        role="img"
                        aria-label={`Laki-laki ${gender.male}, perempuan ${gender.female}`}
                        className="flex h-3 overflow-hidden"
                    >
                        <div className="bg-primary" style={{ width: `${malePercent}%` }} />
                        <div className="flex-1 bg-chart-3" />
                    </div>
                    <dl className="mt-4 grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt className="flex items-center gap-2 text-muted-foreground">
                                <span className="size-2.5 bg-primary" aria-hidden />
                                Laki-laki
                            </dt>
                            <dd className="mt-1 text-xl font-semibold">{gender.male}</dd>
                        </div>
                        <div>
                            <dt className="flex items-center gap-2 text-muted-foreground">
                                <span className="size-2.5 bg-chart-3" aria-hidden />
                                Perempuan
                            </dt>
                            <dd className="mt-1 text-xl font-semibold">{gender.female}</dd>
                        </div>
                    </dl>
                </Panel>

                <Panel title="Kehadiran 6 bulan terakhir" className="lg:col-span-2">
                    <ol className="grid grid-cols-6 items-end gap-3">
                        {attendance.map((point) => (
                            <li key={point.month} className="flex flex-col items-center gap-2">
                                <span className="text-sm font-medium">{point.percent}%</span>
                                <div className="flex h-32 w-full items-end bg-muted">
                                    <div
                                        className="w-full bg-primary"
                                        style={{ height: `${Math.max(0, (point.percent - 80) / 20) * 100}%` }}
                                    />
                                </div>
                                <span className="text-xs text-muted-foreground">{point.month}</span>
                            </li>
                        ))}
                    </ol>
                    <p className="mt-4 text-xs text-muted-foreground">
                        Skala batang dimulai dari 80% agar perbedaan antarbulan terlihat.
                    </p>
                </Panel>
            </div>
        </MasterPage>
    );
}
