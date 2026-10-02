import { router, useForm } from "@inertiajs/react";
import type { FormEvent } from "react";

import {
    store,
    update,
} from "@/actions/Modules/Platform/App/Http/Controllers/Applicant/OnboardingController";
import { destroy as logout } from "@/actions/Modules/Platform/App/Http/Controllers/Applicant/SessionController";
import { Alert, AlertDescription } from "@shared/components/ui/alert";
import { Badge } from "@shared/components/ui/badge";
import { Button } from "@shared/components/ui/button";
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from "@shared/components/ui/field";
import { Input } from "@shared/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@shared/components/ui/select";
import { Textarea } from "@shared/components/ui/textarea";

import ApplicantShell from "../../../Components/ApplicantShell";
import PlanPicker from "../../../Components/PlanPicker";
import type {
    ApplicationData,
    PlanOption,
} from "../../../types/ApplicationData";

const statusLabels: Record<string, string> = {
    pending: "Menunggu persetujuan",
    approved: "Disetujui",
    rejected: "Ditolak",
};

function LogoutButton() {
    return (
        <Button
            type="button"
            variant="outline"
            onClick={() => router.post(logout.url())}
        >
            Keluar
        </Button>
    );
}

/** The submitted school and where its review stands. */
function ApplicationStatus({
    application,
    planName,
}: {
    application: ApplicationData;
    planName: string;
}) {
    return (
        <div className="flex flex-col gap-4">
            <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                <dt className="text-muted-foreground">Sekolah</dt>
                <dd className="font-medium">{application.schoolName}</dd>
                <dt className="text-muted-foreground">Kode sekolah</dt>
                <dd>
                    <code>{application.desiredSlug}</code>
                </dd>
                <dt className="text-muted-foreground">Zona waktu</dt>
                <dd>{application.timezone}</dd>
                <dt className="text-muted-foreground">Paket</dt>
                <dd>{planName}</dd>
                <dt className="text-muted-foreground">Status</dt>
                <dd>
                    <Badge
                        variant={
                            application.status === "approved"
                                ? "default"
                                : "outline"
                        }
                    >
                        {statusLabels[application.status] ?? application.status}
                    </Badge>
                </dd>
            </dl>

            <p className="text-sm text-muted-foreground">
                {application.status === "pending"
                    ? "Tim kami sedang meninjau pengajuan Anda. Kami mengabari lewat email setelah ada keputusan. Trial dimulai saat pengajuan disetujui."
                    : "Sekolah Anda sudah disetujui dan trial sudah berjalan. Keluar, lalu masuk lagi dengan email dan kata sandi yang sama untuk membuka sekolah Anda."}
            </p>

            <div className="flex flex-col gap-3 sm:flex-row">
                {application.status === "approved" ? (
                    <Button
                        type="button"
                        onClick={() => router.post(logout.url())}
                    >
                        Keluar dan masuk ke sekolah
                    </Button>
                ) : (
                    <LogoutButton />
                )}
            </div>
        </div>
    );
}

/**
 * Onboarding: fill in the school, choose a plan and submit for provider
 * review, then follow its status. A rejected application shows the
 * provider's note and the form again, pre-filled, to correct and resubmit.
 */
export default function Onboarding({
    applicant,
    timezones,
    plans,
    trialDays,
    application,
}: {
    applicant: { name: string; email: string };
    timezones: string[];
    plans: PlanOption[];
    trialDays: number;
    application: ApplicationData | null;
}) {
    const rejected = application !== null && application.status === "rejected";
    const planStillOffered =
        application !== null &&
        plans.some((plan) => plan.key === application.planKey);

    const form = useForm({
        school_name: rejected ? application.schoolName : "",
        desired_slug: rejected ? application.desiredSlug : "",
        timezone: rejected
            ? application.timezone
            : (timezones[0] ?? "Asia/Jakarta"),
        plan_key:
            rejected && planStillOffered ? (application.planKey ?? "") : "",
        applicant_message: "",
    });

    const errors = form.errors as typeof form.errors & { application?: string };
    const signedInAs = `Masuk sebagai ${applicant.name} (${applicant.email}).`;

    function submit(event: FormEvent) {
        event.preventDefault();

        if (rejected) {
            form.put(update.url());
        } else {
            form.post(store.url());
        }
    }

    if (application !== null && !rejected) {
        return (
            <ApplicantShell
                title="Pengajuan sekolah"
                description={signedInAs}
                step={4}
                width="max-w-lg"
            >
                <ApplicationStatus
                    application={application}
                    planName={
                        plans.find((plan) => plan.key === application.planKey)
                            ?.name ??
                        application.planKey ??
                        "—"
                    }
                />
            </ApplicantShell>
        );
    }

    return (
        <ApplicantShell
            title="Daftarkan sekolah"
            description={`${signedInAs} Isi data sekolah, pilih paket, lalu ajukan untuk ditinjau.`}
            step={3}
            width="max-w-3xl"
        >
            <form onSubmit={submit} noValidate>
                <FieldGroup>
                    {rejected && (
                        <Alert variant="destructive">
                            <AlertDescription>
                                Pengajuan sebelumnya ditolak
                                {application.adminNote !== null &&
                                application.adminNote !== ""
                                    ? `: ${application.adminNote}`
                                    : "."}{" "}
                                Perbaiki datanya lalu ajukan ulang.
                            </AlertDescription>
                        </Alert>
                    )}

                    {errors.application !== undefined && (
                        <Alert variant="destructive">
                            <AlertDescription>
                                {errors.application}
                            </AlertDescription>
                        </Alert>
                    )}

                    <div className="grid gap-8 xl:grid-cols-2 xl:gap-10">
                        <FieldSet>
                            <FieldLegend>1. Data sekolah</FieldLegend>

                            <Field data-invalid={!!form.errors.school_name}>
                                <FieldLabel htmlFor="school_name">
                                    Nama sekolah
                                </FieldLabel>
                                <Input
                                    id="school_name"
                                    name="school_name"
                                    autoFocus
                                    required
                                    value={form.data.school_name}
                                    aria-invalid={!!form.errors.school_name}
                                    onChange={(event) =>
                                        form.setData(
                                            "school_name",
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldError>
                                    {form.errors.school_name}
                                </FieldError>
                            </Field>

                            <Field data-invalid={!!form.errors.desired_slug}>
                                <FieldLabel htmlFor="desired_slug">
                                    Kode sekolah
                                </FieldLabel>
                                <Input
                                    id="desired_slug"
                                    name="desired_slug"
                                    required
                                    value={form.data.desired_slug}
                                    aria-invalid={!!form.errors.desired_slug}
                                    onChange={(event) =>
                                        form.setData(
                                            "desired_slug",
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldDescription>
                                    Huruf kecil, angka, dan tanda hubung.
                                    Contoh: sma-nusantara
                                </FieldDescription>
                                <FieldError>
                                    {form.errors.desired_slug}
                                </FieldError>
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="timezone">
                                    Zona waktu
                                </FieldLabel>
                                <Select
                                    value={form.data.timezone}
                                    onValueChange={(value) =>
                                        form.setData("timezone", value)
                                    }
                                >
                                    <SelectTrigger id="timezone">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {timezones.map((timezone) => (
                                            <SelectItem
                                                key={timezone}
                                                value={timezone}
                                            >
                                                {timezone}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                        </FieldSet>

                        <FieldSet>
                            <FieldLegend>2. Pilih paket</FieldLegend>
                            <FieldDescription>
                                Sekolah mulai dengan trial {trialDays} hari pada
                                paket yang dipilih, tanpa pembayaran. Trial
                                dimulai saat pengajuan disetujui.
                            </FieldDescription>

                            {plans.length === 0 ? (
                                <Alert>
                                    <AlertDescription>
                                        Belum ada paket yang bisa dipilih.
                                        Hubungi tim kami.
                                    </AlertDescription>
                                </Alert>
                            ) : (
                                <PlanPicker
                                    plans={plans}
                                    value={form.data.plan_key}
                                    onChange={(key) =>
                                        form.setData("plan_key", key)
                                    }
                                    trialDays={trialDays}
                                    invalid={!!form.errors.plan_key}
                                />
                            )}
                            <FieldError>{form.errors.plan_key}</FieldError>
                        </FieldSet>
                    </div>

                    <Field>
                        <FieldLabel htmlFor="applicant_message">
                            Pesan (opsional)
                        </FieldLabel>
                        <Textarea
                            id="applicant_message"
                            name="applicant_message"
                            rows={3}
                            value={form.data.applicant_message}
                            onChange={(event) =>
                                form.setData(
                                    "applicant_message",
                                    event.target.value,
                                )
                            }
                        />
                    </Field>

                    <div className="flex flex-col gap-3 sm:flex-row">
                        <Button
                            type="submit"
                            disabled={form.processing || plans.length === 0}
                        >
                            {form.processing
                                ? "Mengirim..."
                                : rejected
                                  ? "Ajukan ulang"
                                  : "Ajukan sekolah"}
                        </Button>

                        <LogoutButton />
                    </div>
                </FieldGroup>
            </form>
        </ApplicantShell>
    );
}
