import { Head, Link } from '@inertiajs/react';
import { PrinterIcon } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useState } from 'react';

import { show as cardPage } from '@/actions/Modules/Attendance/App/Http/Controllers/StaticQrCardController';
import { EmptyState } from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { cn } from '@shared/lib/utils';

import CardFace from '../../Components/CardFace';
import type { CardTemplate } from '../../Components/CardFace';

interface StaticQrProps {
    schoolName: string;
    /** Shown inside the Siswa list's dialog: no header of its own. */
    embed: boolean;
    template: CardTemplate | null;
    groups: {
        class: string;
        students: { id: number; name: string; nis: string; code: string }[];
    }[];
}

/**
 * Cetak QR Statis: every student's fixed QR laid out for paper, one block
 * per class with a page break between classes. With a card template each
 * QR sits on the school's card picture; otherwise it is a plain sheet.
 * No application shell. "Cetak" opens the browser's print dialog, which
 * also saves a PDF; inside the Siswa list's dialog that button is the
 * dialog's own.
 */
export default function StaticQr({
    schoolName,
    embed,
    template,
    groups,
}: StaticQrProps) {
    const total = groups.reduce((sum, group) => sum + group.students.length, 0);
    const [asCard, setAsCard] = useState(true);
    const showCards = template !== null && asCard;

    const toggle = template !== null && (
        <div
            className="flex gap-1 print:hidden"
            role="group"
            aria-label="Tampilan cetak"
        >
            <Button
                size="sm"
                variant={showCards ? 'default' : 'outline'}
                onClick={() => setAsCard(true)}
            >
                Kartu
            </Button>
            <Button
                size="sm"
                variant={showCards ? 'outline' : 'default'}
                onClick={() => setAsCard(false)}
            >
                QR saja
            </Button>
        </div>
    );

    return (
        <div
            className={cn(
                'mx-auto min-h-screen max-w-5xl bg-background text-foreground print:max-w-none print:p-0',
                embed ? 'p-3' : 'p-6',
            )}
        >
            <Head title="Cetak QR Statis" />

            {embed ? (
                toggle !== false && <div className="mb-3">{toggle}</div>
            ) : (
                <header className="flex items-start justify-between gap-4 print:hidden">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            {schoolName}
                        </p>
                        <h1 className="mt-1 text-xl font-semibold">
                            Cetak QR Statis
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {total} siswa aktif. QR hanya diterima saat QR
                            statis menyala di Pengaturan Absensi.
                        </p>
                        <div className="mt-3 flex flex-wrap items-center gap-2">
                            {toggle}
                            <Button asChild size="sm" variant="link">
                                <Link href={cardPage.url()}>
                                    Atur template kartu
                                </Link>
                            </Button>
                        </div>
                    </div>
                    <Button
                        onClick={() => window.print()}
                        disabled={total === 0}
                    >
                        <PrinterIcon />
                        Cetak
                    </Button>
                </header>
            )}

            <div className={cn('flex flex-col gap-8', !embed && 'mt-6')}>
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
                            {showCards ? (
                                <div className="flex flex-wrap gap-4">
                                    {group.students.map((student) => (
                                        <div
                                            key={student.id}
                                            className="break-inside-avoid"
                                            data-testid="static-qr-card"
                                        >
                                            <CardFace
                                                imageUrl={template.imageUrl}
                                                aspect={template.aspect}
                                                x={template.x}
                                                y={template.y}
                                                size={template.size}
                                                widthMm={template.widthMm}
                                            >
                                                <QRCodeSVG
                                                    value={student.code}
                                                    level="M"
                                                    marginSize={1}
                                                    bgColor="#ffffff"
                                                    fgColor="#000000"
                                                    title={`QR ${student.name}`}
                                                    style={{
                                                        width: '100%',
                                                        height: '100%',
                                                        display: 'block',
                                                    }}
                                                />
                                            </CardFace>
                                        </div>
                                    ))}
                                </div>
                            ) : (
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
                                                    NIS {student.nis} ·{' '}
                                                    {group.class}
                                                </span>
                                            </figcaption>
                                        </figure>
                                    ))}
                                </div>
                            )}
                        </section>
                    ))
                )}
            </div>
        </div>
    );
}
