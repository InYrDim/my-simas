import { Link, useForm } from '@inertiajs/react';

import { update } from '@/actions/Modules/Attendance/App/Http/Controllers/SettingsController';
import { whatsapp } from '@/routes/core/integration';
import { Panel } from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { Checkbox } from '@shared/components/ui/checkbox';
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';

import AttendancePage from '../../Components/AttendancePage';

/**
 * Pengaturan Absensi: which kinds of attendance the school uses, and the
 * last minute a student still arrives on time.
 */
export default function Settings({
    lateAfter,
    gateEnabled,
    lessonEnabled,
    lessonScanEarlyMinutes,
    gateOpensAt,
    gateClosesAt,
    can,
}: {
    lateAfter: string;
    gateEnabled: boolean;
    lessonEnabled: boolean;
    lessonScanEarlyMinutes: number;
    gateOpensAt: string;
    gateClosesAt: string;
    can: { manageNotices: boolean };
}) {
    const form = useForm({
        late_after: lateAfter,
        gate_enabled: gateEnabled,
        lesson_enabled: lessonEnabled,
        lesson_scan_early_minutes: lessonScanEarlyMinutes,
        gate_opens_at: gateOpensAt,
        gate_closes_at: gateClosesAt,
    });

    return (
        <AttendancePage
            title="Pengaturan Absensi"
            description="Jenis absensi yang dipakai sekolah dan aturan jam masuk."
            width="max-w-2xl"
        >
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(update.url(), {
                        preserveScroll: true,
                        onSuccess: () => form.setDefaults(),
                    });
                }}
            >
                <Panel title="Jenis absensi">
                    <div className="flex flex-col gap-4">
                        <Field
                            orientation="horizontal"
                            data-invalid={
                                form.errors.gate_enabled !== undefined
                            }
                        >
                            <Checkbox
                                id="gate-enabled"
                                checked={form.data.gate_enabled}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'gate_enabled',
                                        checked === true,
                                    )
                                }
                            />
                            <FieldContent>
                                <FieldLabel htmlFor="gate-enabled">
                                    Absensi gerbang
                                </FieldLabel>
                                <FieldDescription>
                                    Mencatat siswa masuk dan pulang di gerbang,
                                    dengan QR atau pilih nama. Jika mati, mode
                                    Masuk dan Pulang di Pindai QR tidak
                                    tersedia.
                                </FieldDescription>
                                {form.errors.gate_enabled !== undefined && (
                                    <FieldError>
                                        {form.errors.gate_enabled}
                                    </FieldError>
                                )}
                            </FieldContent>
                        </Field>
                        <Field
                            orientation="horizontal"
                            data-invalid={
                                form.errors.lesson_enabled !== undefined
                            }
                        >
                            <Checkbox
                                id="lesson-enabled"
                                checked={form.data.lesson_enabled}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'lesson_enabled',
                                        checked === true,
                                    )
                                }
                            />
                            <FieldContent>
                                <FieldLabel htmlFor="lesson-enabled">
                                    Absensi jam pelajaran
                                </FieldLabel>
                                <FieldDescription>
                                    Mencatat kehadiran siswa di tiap jam
                                    pelajaran. Jika mati, menu Jam Pelajaran dan
                                    mode Jam pelajaran di Pindai QR
                                    disembunyikan.
                                </FieldDescription>
                                {form.errors.lesson_enabled !== undefined && (
                                    <FieldError>
                                        {form.errors.lesson_enabled}
                                    </FieldError>
                                )}
                            </FieldContent>
                        </Field>
                        <p className="text-sm text-muted-foreground">
                            Data yang sudah tercatat tidak dihapus saat sebuah
                            jenis dimatikan.
                        </p>
                    </div>
                </Panel>

                <Panel title="Batas jam masuk" className="mt-6">
                    <Field data-invalid={form.errors.late_after !== undefined}>
                        <FieldLabel htmlFor="late-after">
                            Tepat waktu sampai pukul
                        </FieldLabel>
                        <Input
                            id="late-after"
                            type="time"
                            className="w-40"
                            value={form.data.late_after}
                            onChange={(event) =>
                                form.setData('late_after', event.target.value)
                            }
                            aria-invalid={form.errors.late_after !== undefined}
                        />
                        <FieldDescription>
                            Siswa yang tercatat masuk setelah menit ini
                            berstatus Terlambat.
                        </FieldDescription>
                        {form.errors.late_after !== undefined && (
                            <FieldError>{form.errors.late_after}</FieldError>
                        )}
                    </Field>
                </Panel>

                <Panel title="Jam gerbang" className="mt-6">
                    <div className="flex flex-col gap-4 sm:flex-row">
                        <Field
                            data-invalid={form.errors.gate_opens_at !== undefined}
                        >
                            <FieldLabel htmlFor="gate-opens-at">
                                Gerbang dibuka pukul
                            </FieldLabel>
                            <Input
                                id="gate-opens-at"
                                type="time"
                                className="w-40"
                                value={form.data.gate_opens_at}
                                onChange={(event) =>
                                    form.setData(
                                        'gate_opens_at',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={
                                    form.errors.gate_opens_at !== undefined
                                }
                            />
                            {form.errors.gate_opens_at !== undefined && (
                                <FieldError>
                                    {form.errors.gate_opens_at}
                                </FieldError>
                            )}
                        </Field>
                        <Field
                            data-invalid={
                                form.errors.gate_closes_at !== undefined
                            }
                        >
                            <FieldLabel htmlFor="gate-closes-at">
                                Gerbang ditutup pukul
                            </FieldLabel>
                            <Input
                                id="gate-closes-at"
                                type="time"
                                className="w-40"
                                value={form.data.gate_closes_at}
                                onChange={(event) =>
                                    form.setData(
                                        'gate_closes_at',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={
                                    form.errors.gate_closes_at !== undefined
                                }
                            />
                            {form.errors.gate_closes_at !== undefined && (
                                <FieldError>
                                    {form.errors.gate_closes_at}
                                </FieldError>
                            )}
                        </Field>
                    </div>
                    <FieldDescription className="mt-2">
                        Scan masuk dan pulang hanya diterima di antara dua
                        jam ini. Pulang sebelum jam pelajaran terakhir selesai
                        ditandai Pulang awal. Di luar jam gerbang, ubah lewat
                        Input harian.
                    </FieldDescription>
                </Panel>

                <Panel title="Pindai jam pelajaran" className="mt-6">
                    <Field
                        data-invalid={
                            form.errors.lesson_scan_early_minutes !== undefined
                        }
                    >
                        <FieldLabel htmlFor="lesson-scan-early-minutes">
                            Boleh dipindai sebelum jam mulai (menit)
                        </FieldLabel>
                        <Input
                            id="lesson-scan-early-minutes"
                            type="number"
                            min={0}
                            max={30}
                            className="w-40"
                            value={form.data.lesson_scan_early_minutes}
                            onChange={(event) =>
                                form.setData(
                                    'lesson_scan_early_minutes',
                                    Number(event.target.value),
                                )
                            }
                            aria-invalid={
                                form.errors.lesson_scan_early_minutes !==
                                undefined
                            }
                        />
                        <FieldDescription>
                            Absensi jam pelajaran hanya bisa dipindai mulai
                            sekian menit sebelum jam mulai sampai jam
                            selesai. Di luar itu, ubah lewat Riwayat Absensi.
                        </FieldDescription>
                        {form.errors.lesson_scan_early_minutes !==
                            undefined && (
                            <FieldError>
                                {form.errors.lesson_scan_early_minutes}
                            </FieldError>
                        )}
                    </Field>
                </Panel>

                <div className="mt-6">
                    <Button
                        type="submit"
                        disabled={!form.isDirty || form.processing}
                    >
                        Simpan
                    </Button>
                </div>
            </form>

            <Panel title="Pemberitahuan ke wali murid" className="mt-6">
                <p className="text-sm text-muted-foreground">
                    Pesan WhatsApp saat siswa masuk, pulang, tidak hadir, atau
                    alpa di jam pelajaran diatur per jenis di Integrasi ›
                    WhatsApp. Semua jenis mati sampai sekolah menyalakannya.
                </p>
                {can.manageNotices && (
                    <Button asChild variant="outline" className="mt-4">
                        <Link href={whatsapp.url()}>
                            Buka Integrasi › WhatsApp
                        </Link>
                    </Button>
                )}
            </Panel>
        </AttendancePage>
    );
}
