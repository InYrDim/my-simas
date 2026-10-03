import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import {
    archive as archivePlan,
    restore as restorePlan,
    store as storePlan,
    update as updatePlan,
} from '@/actions/Modules/Platform/App/Http/Controllers/PlanController';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Checkbox } from '@shared/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@shared/components/ui/dialog';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';

import { consolePath } from '../../../Components/consolePath';
import {
    EmptyState,
    PageHeader,
    Panel,
} from '../../../Components/ConsoleParts';
import { formatRupiah } from '../../../Components/format';
import ProviderLayout from '../../../Components/ProviderLayout';
import { send } from '../../../Components/send';
import type { ConsolePlan, ModuleOption } from '../../../types/console';

interface PlansProps {
    plans: ConsolePlan[];
    modules: ModuleOption[];
}

/** `null` = closed, `'new'` = create dialog, a plan = edit dialog. */
type Editing = ConsolePlan | 'new' | null;

/**
 * Plan catalogue (kelola paket). Create and edit happen in the shared
 * Dialog over the list; archive and restore act in place.
 */
export default function BillingPlans({ plans, modules }: PlansProps) {
    const [editing, setEditing] = useState<Editing>(null);

    const moduleLabel = (key: string) =>
        modules.find((module) => module.key === key)?.label ?? key;

    const close = () => setEditing(null);

    return (
        <ProviderLayout>
            <Head title="Paket" />

            <PageHeader
                title="Paket"
                description="Katalog paket yang bisa dipilih sekolah."
                actions={
                    <Button onClick={() => setEditing('new')}>
                        Buat paket
                    </Button>
                }
            />

            {plans.length === 0 ? (
                <div className="mt-8">
                    <EmptyState>
                        Belum ada paket. Buat paket pertama agar tenant bisa
                        berlangganan.
                    </EmptyState>
                </div>
            ) : (
                <div className="mt-8 grid gap-6 lg:grid-cols-3">
                    {plans.map((plan) => {
                        const saving =
                            plan.priceMonthly * 12 - plan.priceYearly;

                        return (
                            <Panel key={plan.id} title={plan.name}>
                                <div className="flex flex-wrap gap-2">
                                    {plan.archived ? (
                                        <Badge variant="secondary">
                                            diarsipkan
                                        </Badge>
                                    ) : !plan.isActive ? (
                                        <Badge variant="secondary">
                                            nonaktif
                                        </Badge>
                                    ) : null}
                                </div>
                                <p className="mt-1 text-xl font-semibold">
                                    {formatRupiah(plan.priceMonthly)}
                                    <span className="text-sm font-normal text-muted-foreground">
                                        {' '}
                                        / bulan
                                    </span>
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {formatRupiah(plan.priceYearly)} / tahun
                                </p>
                                {saving > 0 && (
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Hemat {formatRupiah(saving)} bila
                                        tahunan
                                    </p>
                                )}

                                <p className="mt-5 text-xs text-muted-foreground">
                                    Batas pengguna
                                </p>
                                <p className="text-sm">
                                    {plan.maxUsers === null
                                        ? 'Tanpa batas'
                                        : plan.maxUsers}
                                </p>

                                <p className="mt-4 text-xs text-muted-foreground">
                                    Modul
                                </p>
                                <ul className="mt-1 text-sm">
                                    {plan.modules.map((key) => (
                                        <li key={key}>{moduleLabel(key)}</li>
                                    ))}
                                </ul>

                                <p className="mt-4 text-xs text-muted-foreground">
                                    {plan.subscribers ?? 0} sekolah berlangganan
                                </p>

                                <div className="mt-5 flex flex-wrap gap-3">
                                    <Button
                                        variant="outline"
                                        onClick={() => setEditing(plan)}
                                    >
                                        Ubah
                                    </Button>
                                    {plan.archived ? (
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                send(
                                                    'post',
                                                    restorePlan.url({
                                                        plan: plan.id,
                                                    }),
                                                )
                                            }
                                        >
                                            Pulihkan
                                        </Button>
                                    ) : (
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                send(
                                                    'post',
                                                    archivePlan.url({
                                                        plan: plan.id,
                                                    }),
                                                )
                                            }
                                        >
                                            Arsipkan
                                        </Button>
                                    )}
                                </div>
                            </Panel>
                        );
                    })}
                </div>
            )}

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        close();
                    }
                }}
            >
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
                    {editing !== null && (
                        <PlanForm
                            key={editing === 'new' ? 'new' : editing.id}
                            plan={editing === 'new' ? null : editing}
                            modules={modules}
                            onDone={close}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </ProviderLayout>
    );
}

function PlanForm({
    plan,
    modules,
    onDone,
}: {
    plan: ConsolePlan | null;
    modules: ModuleOption[];
    onDone: () => void;
}) {
    const form = useForm({
        key: plan?.key ?? '',
        name: plan?.name ?? '',
        price_monthly: plan?.priceMonthly ?? 0,
        price_yearly: plan?.priceYearly ?? 0,
        max_users: plan?.maxUsers ?? ('' as number | ''),
        modules: plan?.modules ?? ['core'],
        is_active: plan?.isActive ?? true,
        sort_order: plan?.sortOrder ?? 0,
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: onDone };

        if (plan === null) {
            form.post(consolePath(storePlan.url()), options);

            return;
        }

        form.put(consolePath(updatePlan.url({ plan: plan.id })), options);
    }

    function toggleModule(key: string, checked: boolean) {
        form.setData(
            'modules',
            checked
                ? [...form.data.modules, key]
                : form.data.modules.filter((module) => module !== key),
        );
    }

    return (
        <form onSubmit={submit} noValidate>
            <DialogHeader>
                <DialogTitle>
                    {plan === null ? 'Buat paket' : `Ubah ${plan.name}`}
                </DialogTitle>
                <DialogDescription>
                    Harga dalam rupiah. Perubahan harga berlaku untuk tagihan
                    berikutnya.
                </DialogDescription>
            </DialogHeader>

            <FieldGroup className="my-5">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field data-invalid={!!form.errors.name}>
                        <FieldLabel htmlFor="plan-name">Nama paket</FieldLabel>
                        <Input
                            id="plan-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            aria-invalid={!!form.errors.name}
                            autoFocus
                            required
                        />
                        <FieldError>{form.errors.name}</FieldError>
                    </Field>
                    <Field data-invalid={!!form.errors.key}>
                        <FieldLabel htmlFor="plan-key">Kode paket</FieldLabel>
                        <Input
                            id="plan-key"
                            value={form.data.key}
                            onChange={(event) =>
                                form.setData('key', event.target.value)
                            }
                            disabled={plan !== null}
                            className="font-mono"
                            aria-invalid={!!form.errors.key}
                            required
                        />
                        {form.errors.key ? (
                            <FieldError>{form.errors.key}</FieldError>
                        ) : (
                            <FieldDescription>
                                {plan === null
                                    ? 'Huruf kecil, angka, tanda hubung.'
                                    : 'Tidak bisa diubah.'}
                            </FieldDescription>
                        )}
                    </Field>
                    <Field data-invalid={!!form.errors.price_monthly}>
                        <FieldLabel htmlFor="plan-monthly">
                            Harga bulanan (Rp)
                        </FieldLabel>
                        <Input
                            id="plan-monthly"
                            type="number"
                            min={0}
                            value={form.data.price_monthly}
                            onChange={(event) =>
                                form.setData(
                                    'price_monthly',
                                    Number(event.target.value),
                                )
                            }
                            aria-invalid={!!form.errors.price_monthly}
                        />
                        <FieldError>{form.errors.price_monthly}</FieldError>
                    </Field>
                    <Field data-invalid={!!form.errors.price_yearly}>
                        <FieldLabel htmlFor="plan-yearly">
                            Harga tahunan (Rp)
                        </FieldLabel>
                        <Input
                            id="plan-yearly"
                            type="number"
                            min={0}
                            value={form.data.price_yearly}
                            onChange={(event) =>
                                form.setData(
                                    'price_yearly',
                                    Number(event.target.value),
                                )
                            }
                            aria-invalid={!!form.errors.price_yearly}
                        />
                        <FieldError>{form.errors.price_yearly}</FieldError>
                    </Field>
                    <Field data-invalid={!!form.errors.max_users}>
                        <FieldLabel htmlFor="plan-users">
                            Batas pengguna
                        </FieldLabel>
                        <Input
                            id="plan-users"
                            type="number"
                            min={1}
                            value={form.data.max_users}
                            onChange={(event) =>
                                form.setData(
                                    'max_users',
                                    event.target.value === ''
                                        ? ''
                                        : Number(event.target.value),
                                )
                            }
                            aria-invalid={!!form.errors.max_users}
                        />
                        {form.errors.max_users ? (
                            <FieldError>{form.errors.max_users}</FieldError>
                        ) : (
                            <FieldDescription>
                                Kosongkan untuk tanpa batas.
                            </FieldDescription>
                        )}
                    </Field>
                    <Field data-invalid={!!form.errors.sort_order}>
                        <FieldLabel htmlFor="plan-order">
                            Urutan tampil
                        </FieldLabel>
                        <Input
                            id="plan-order"
                            type="number"
                            min={0}
                            value={form.data.sort_order}
                            onChange={(event) =>
                                form.setData(
                                    'sort_order',
                                    Number(event.target.value),
                                )
                            }
                            aria-invalid={!!form.errors.sort_order}
                        />
                        <FieldError>{form.errors.sort_order}</FieldError>
                    </Field>
                </div>

                <FieldSet>
                    <FieldLegend variant="label">
                        Modul yang termasuk
                    </FieldLegend>
                    <FieldGroup className="gap-3">
                        {modules.map((module) => (
                            <Field key={module.key} orientation="horizontal">
                                <Checkbox
                                    id={`plan-module-${module.key}`}
                                    checked={
                                        module.alwaysActive ||
                                        form.data.modules.includes(module.key)
                                    }
                                    disabled={module.alwaysActive}
                                    onCheckedChange={(checked) =>
                                        toggleModule(
                                            module.key,
                                            checked === true,
                                        )
                                    }
                                />
                                <FieldLabel
                                    htmlFor={`plan-module-${module.key}`}
                                >
                                    {module.label}
                                    {module.alwaysActive && (
                                        <span className="font-normal text-muted-foreground">
                                            {' '}
                                            selalu aktif
                                        </span>
                                    )}
                                </FieldLabel>
                            </Field>
                        ))}
                    </FieldGroup>
                    <FieldError>{form.errors.modules}</FieldError>
                </FieldSet>

                <Field orientation="horizontal">
                    <Checkbox
                        id="plan-active"
                        checked={form.data.is_active}
                        onCheckedChange={(checked) =>
                            form.setData('is_active', checked === true)
                        }
                    />
                    <FieldLabel htmlFor="plan-active">
                        Paket aktif (bisa dipilih untuk langganan baru)
                    </FieldLabel>
                </Field>
            </FieldGroup>

            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    Batal
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {plan === null ? 'Buat paket' : 'Simpan perubahan'}
                </Button>
            </DialogFooter>
        </form>
    );
}
