import { Head } from '@inertiajs/react';
import { PrinterIcon } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';

import { EmptyState } from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';

interface StaticQrProps {
    schoolName: string;
    groups: {
        class: string;
        students: { id: number; name: string; nis: string; code: string }[];
    }[];
}

/**
 * Cetak QR Statis: every student's fixed QR laid out for paper, one block
 * per class with a page break between classes. No application shell;
 * "Cetak" opens the browser's print dialog, which also saves a PDF.
 */
export default function StaticQr({ schoolName, groups }: StaticQrProps) {
    const total = groups.reduce((sum, group) => sum + group.students.length, 0);

    return (
        <div className="mx-auto min-h-screen max-w-5xl bg-background p-6 text-foreground print:max-w-none print:p-0">
            <Head title="Cetak QR Statis" />

            <header className="flex items-start justify-between gap-4 print:hidden">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {schoolName}
                    </p>
                    <h1 className="mt-1 text-xl font-semibold">
                        Cetak QR Statis
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {total} siswa aktif. QR hanya diterima saat QR statis
                        menyala di Pengaturan Absensi.
                    </p>
                </div>
                <Button onClick={() => window.print()} disabled={total === 0}>
                    <PrinterIcon />
                    Cetak
                </Button>
            </header>

            <div className="mt-6 flex flex-col gap-8">
                {groups.length === 0 ? (
                    <EmptyState>
                        Belum ada siswa aktif di kelas tahun ajaran ini.
                    </EmptyState>
                ) : (
                    groups.map((group) => (
                        <section
                            key={group.class}
                            className="break-after-page last:break-after-auto"
                        >
                            <h2 className="mb-3 text-lg font-semibold">
                                {group.class}
                            </h2>
                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                {group.students.map((student) => (
                                    <figure
                                        key={student.id}
                                        className="flex break-inside-avoid flex-col items-center gap-2 rounded-md border p-3 text-center"
                                        data-testid="static-qr-card"
                                    >
                                        {/* Always dark on white: a scanner needs the contrast in any theme. */}
                                        <div className="bg-white p-2">
                                            <QRCodeSVG
                                                value={student.code}
                                                size={144}
                                                level="M"
                                                bgColor="#ffffff"
                                                fgColor="#000000"
                                                title={`QR ${student.name}`}
                                            />
                                        </div>
                                        <figcaption className="text-sm">
                                            <span className="block font-medium">
                                                {student.name}
                                            </span>
                                            <span className="text-muted-foreground">
                                                NIS {student.nis} · {group.class}
                                            </span>
                                        </figcaption>
                                    </figure>
                                ))}
                            </div>
                        </section>
                    ))
                )}
            </div>
        </div>
    );
}
