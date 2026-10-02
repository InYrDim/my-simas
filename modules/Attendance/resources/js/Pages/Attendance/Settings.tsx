import { Link, useForm } from '@inertiajs/react';

import { update } from '@/actions/Modules/Attendance/App/Http/Controllers/SettingsController';
import { whatsapp } from '@/routes/core/integration';
import { Panel } from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';

import AttendancePage from '../../Components/AttendancePage';

/** Pengaturan Absensi: the last minute a student still arrives on time. */
export default function Settings({
    lateAfter,
    can,
}: {
    lateAfter: string;
    can: { manageNotices: boolean };
}) {
    const form = useForm({ late_after: lateAfter });

    return (
        <AttendancePage
            title="Pengaturan Absensi"
            description="Aturan yang dipakai saat mencatat siswa di gerbang."
            width="max-w-2xl"
        >
            <Panel title="Batas jam masuk">
                <form
                    className="flex flex-col gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(update.url(), {
                            preserveScroll: true,
                            onSuccess: () => form.setDefaults(),
                        });
                    }}
                >
                    <Field data-invalid={form.errors.late_after !== undefined}>
                        <FieldLabel htmlFor="late-after">Tepat waktu sampai pukul</FieldLabel>
                        <Input
                            id="late-after"
                            type="time"
                            className="w-40"
                            value={form.data.late_after}
                            onChange={(event) => form.setData('late_after', event.target.value)}
                            aria-invalid={form.errors.late_after !== undefined}
                        />
                        <FieldDescription>
                            Siswa yang tercatat masuk setelah menit ini berstatus Terlambat.
                        </FieldDescription>
                        {form.errors.late_after !== undefined && (
                            <FieldError>{form.errors.late_after}</FieldError>
                        )}
                    </Field>
                    <div>
                        <Button type="submit" disabled={!form.isDirty || form.processing}>
                            Simpan
                        </Button>
                    </div>
                </form>
            </Panel>

            <Panel title="Pemberitahuan ke wali murid" className="mt-6">
                <p className="text-sm text-muted-foreground">
                    Pesan WhatsApp saat siswa masuk, pulang, tidak hadir, atau alpa di jam
                    pelajaran diatur per jenis di Integrasi › WhatsApp. Semua jenis mati
                    sampai sekolah menyalakannya.
                </p>
                {can.manageNotices && (
                    <Button asChild variant="outline" className="mt-4">
                        <Link href={whatsapp.url()}>Buka Integrasi › WhatsApp</Link>
                    </Button>
                )}
            </Panel>
        </AttendancePage>
    );
}
