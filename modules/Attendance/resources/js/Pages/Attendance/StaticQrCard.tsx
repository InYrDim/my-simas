import { Link, router, useForm } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import { useRef } from 'react';
import type { KeyboardEvent, PointerEvent } from 'react';

import {
    destroy,
    store,
    update,
} from '@/actions/Modules/Attendance/App/Http/Controllers/StaticQrCardController';
import { index as studentsPage } from '@/actions/Modules/Core/App/Http/Controllers/StudentController';
import { Panel } from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@shared/components/ui/alert-dialog';
import { Button } from '@shared/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';

import AttendancePage from '../../Components/AttendancePage';
import CardFace from '../../Components/CardFace';
import type { CardTemplate } from '../../Components/CardFace';

/** Smallest QR side, in millimetres, that phone cameras still read well. */
const MIN_READABLE_MM = 15;

const clamp = (value: number, min: number, max: number) =>
    Math.min(Math.max(value, min), max);

/**
 * Template Kartu: the picture of the student card and the place of the QR
 * on it. The box is dragged on the picture; its place is kept as
 * percentages, so it does not depend on the picture's resolution.
 */
export default function StaticQrCard({
    template,
    maxMegabytes,
    minSize,
}: {
    template: CardTemplate | null;
    maxMegabytes: number;
    minSize: number;
}) {
    const upload = useForm<{ image: File | null }>({ image: null });

    return (
        <AttendancePage
            title="Template Kartu"
            description="Tempel QR statis di gambar kartu siswa sekolah."
            width="max-w-3xl"
            actions={
                <Button asChild variant="outline">
                    <Link href={studentsPage.url()}>Ke daftar siswa</Link>
                </Button>
            }
        >
            {template === null ? (
                <Panel title="Gambar kartu">
                    <p className="text-sm text-muted-foreground">
                        Unggah gambar kartu siswa (PNG atau JPG, maksimal{' '}
                        {maxMegabytes} MB, minimal 300 × 180 piksel). Setelah
                        itu Anda menentukan letak QR di atasnya.
                    </p>
                    <ImagePicker
                        form={upload}
                        label="Pilih gambar kartu"
                        className="mt-4"
                    />
                </Panel>
            ) : (
                <Editor
                    key={template.imageUrl}
                    template={template}
                    minSize={minSize}
                    upload={upload}
                />
            )}
        </AttendancePage>
    );
}

function ImagePicker({
    form,
    label,
    className,
}: {
    form: ReturnType<typeof useForm<{ image: File | null }>>;
    label: string;
    className?: string;
}) {
    return (
        <Field
            className={className}
            data-invalid={form.errors.image !== undefined}
        >
            <FieldLabel htmlFor="card-image">{label}</FieldLabel>
            <Input
                id="card-image"
                type="file"
                accept="image/png,image/jpeg"
                disabled={form.processing}
                aria-invalid={form.errors.image !== undefined}
                onChange={(event) => {
                    const file = event.target.files?.[0];

                    if (file === undefined) {
                        return;
                    }

                    const input = event.target;

                    // The file is chosen now, so the form's data (still null)
                    // is swapped for it at submit time.
                    form.transform(() => ({ image: file }));
                    form.post(store.url(), {
                        forceFormData: true,
                        preserveScroll: true,
                        onFinish: () => {
                            input.value = '';
                        },
                    });
                }}
            />
            {form.errors.image !== undefined && (
                <FieldError>{form.errors.image}</FieldError>
            )}
        </Field>
    );
}

function Editor({
    template,
    minSize,
    upload,
}: {
    template: CardTemplate;
    minSize: number;
    upload: ReturnType<typeof useForm<{ image: File | null }>>;
}) {
    const form = useForm({
        qr_x: template.x,
        qr_y: template.y,
        qr_size: template.size,
        card_width_mm: template.widthMm,
    });
    const face = useRef<HTMLDivElement>(null);
    const drag = useRef<{
        pointer: number;
        startX: number;
        startY: number;
        x: number;
        y: number;
    } | null>(null);

    const boxHeight = (size: number) => size * template.aspect;
    const maxSize = Math.min(100, 100 / template.aspect);
    const maxX = (size: number) => 100 - size;
    const maxY = (size: number) => 100 - boxHeight(size);

    const moveTo = (x: number, y: number) => {
        form.setData((data) => ({
            ...data,
            qr_x: clamp(x, 0, maxX(data.qr_size)),
            qr_y: clamp(y, 0, maxY(data.qr_size)),
        }));
    };

    const onPointerDown = (event: PointerEvent<HTMLDivElement>) => {
        event.currentTarget.setPointerCapture(event.pointerId);
        drag.current = {
            pointer: event.pointerId,
            startX: event.clientX,
            startY: event.clientY,
            x: form.data.qr_x,
            y: form.data.qr_y,
        };
    };

    const onPointerMove = (event: PointerEvent<HTMLDivElement>) => {
        const start = drag.current;
        const rect = face.current?.getBoundingClientRect();

        if (
            start === null ||
            rect === undefined ||
            start.pointer !== event.pointerId
        ) {
            return;
        }

        moveTo(
            start.x + ((event.clientX - start.startX) / rect.width) * 100,
            start.y + ((event.clientY - start.startY) / rect.height) * 100,
        );
    };

    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        const step = event.shiftKey ? 2 : 0.5;
        const moves: Record<string, [number, number]> = {
            ArrowLeft: [-step, 0],
            ArrowRight: [step, 0],
            ArrowUp: [0, -step],
            ArrowDown: [0, step],
        };
        const move = moves[event.key];

        if (move !== undefined) {
            event.preventDefault();
            moveTo(form.data.qr_x + move[0], form.data.qr_y + move[1]);
        }
    };

    const setSize = (value: number) => {
        const size = clamp(value, minSize, maxSize);

        form.setData((data) => ({
            ...data,
            qr_size: size,
            qr_x: clamp(data.qr_x, 0, maxX(size)),
            qr_y: clamp(data.qr_y, 0, maxY(size)),
        }));
    };

    const sideMm = (form.data.qr_size / 100) * form.data.card_width_mm;
    const placementError = form.errors.qr_x ?? form.errors.qr_size;

    return (
        <>
            <Panel title="Letak QR">
                <p className="mb-4 text-sm text-muted-foreground">
                    Geser kotak QR ke tempat yang diinginkan. Tombol panah
                    menggeser kotak yang sedang dipilih (Shift: lebih jauh).
                </p>
                <div className="mx-auto max-w-xl">
                    <CardFace
                        containerRef={face}
                        imageUrl={template.imageUrl}
                        aspect={template.aspect}
                        x={form.data.qr_x}
                        y={form.data.qr_y}
                        size={form.data.qr_size}
                        boxProps={{
                            className:
                                'cursor-move touch-none bg-white outline-2 outline-primary outline-dashed focus-visible:outline-solid',
                            tabIndex: 0,
                            'aria-label':
                                'Kotak QR. Geser dengan penunjuk atau tombol panah.',
                            onPointerDown,
                            onPointerMove,
                            onPointerUp: () => {
                                drag.current = null;
                            },
                            onKeyDown,
                        }}
                    >
                        <QRCodeSVG
                            value="CONTOH-QR-STATIS"
                            level="M"
                            marginSize={1}
                            bgColor="#ffffff"
                            fgColor="#000000"
                            title="Contoh QR"
                            style={{
                                width: '100%',
                                height: '100%',
                                display: 'block',
                                pointerEvents: 'none',
                            }}
                        />
                    </CardFace>
                </div>
                <p className="mt-2 text-center text-xs text-muted-foreground">
                    QR contoh; kode siswa dibuat saat dicetak.
                </p>

                <div className="mt-6 flex flex-col gap-4 sm:flex-row">
                    <Field data-invalid={placementError !== undefined}>
                        <FieldLabel htmlFor="card-qr-size">
                            Ukuran QR ({form.data.qr_size.toFixed(0)}% lebar
                            kartu)
                        </FieldLabel>
                        <Input
                            id="card-qr-size"
                            type="range"
                            min={minSize}
                            max={maxSize}
                            step={0.5}
                            className="h-auto p-0"
                            value={form.data.qr_size}
                            onChange={(event) =>
                                setSize(Number(event.target.value))
                            }
                        />
                        {placementError !== undefined && (
                            <FieldError>{placementError}</FieldError>
                        )}
                    </Field>
                    <Field
                        data-invalid={form.errors.card_width_mm !== undefined}
                    >
                        <FieldLabel htmlFor="card-width-mm">
                            Lebar kartu saat dicetak (mm)
                        </FieldLabel>
                        <Input
                            id="card-width-mm"
                            type="number"
                            min={40}
                            max={300}
                            step={0.1}
                            className="w-40"
                            value={form.data.card_width_mm}
                            onChange={(event) =>
                                form.setData(
                                    'card_width_mm',
                                    Number(event.target.value),
                                )
                            }
                            aria-invalid={
                                form.errors.card_width_mm !== undefined
                            }
                        />
                        <FieldDescription>
                            Kartu bank/KTP: 85,6 mm. Tinggi mengikuti gambar.
                        </FieldDescription>
                        {form.errors.card_width_mm !== undefined && (
                            <FieldError>{form.errors.card_width_mm}</FieldError>
                        )}
                    </Field>
                </div>

                {sideMm < MIN_READABLE_MM && (
                    <Alert className="mt-4">
                        <AlertDescription>
                            QR akan tercetak sekitar {sideMm.toFixed(1)} mm. Di
                            bawah {MIN_READABLE_MM} mm kamera ponsel sering
                            gagal membacanya; perbesar QR atau lebar kartu.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="mt-6 flex flex-wrap gap-2">
                    <Button
                        disabled={!form.isDirty || form.processing}
                        onClick={() =>
                            form.put(update.url(), {
                                preserveScroll: true,
                                onSuccess: () => form.setDefaults(),
                            })
                        }
                    >
                        Simpan letak QR
                    </Button>
                </div>
            </Panel>

            <Panel title="Gambar kartu" className="mt-6">
                <p className="text-sm text-muted-foreground">
                    {template.imageName}. Mengganti gambar mengembalikan kotak
                    QR ke tengah.
                </p>
                <ImagePicker
                    form={upload}
                    label="Ganti gambar kartu"
                    className="mt-4"
                />
                <AlertDialog>
                    <AlertDialogTrigger asChild>
                        <Button variant="destructive" className="mt-4">
                            Hapus template
                        </Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                Hapus template kartu?
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                Gambar kartu dan letak QR dihapus. Cetak QR
                                statis kembali berupa lembar QR polos.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Batal</AlertDialogCancel>
                            <AlertDialogAction
                                onClick={() =>
                                    router.delete(destroy.url(), {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                Hapus
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </Panel>
        </>
    );
}
