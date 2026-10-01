import { Head } from '@inertiajs/react';
import { useState } from 'react';

import ProviderLayout from '../../../Components/ProviderLayout';
import { PageHeader, Panel, buttonGhost } from '../../../Components/ui';
import { formatRupiah } from '../../../Components/format';
import type { ConsoleModule, ConsolePlan } from '../../../types/console';

interface PlansProps {
    plans: ConsolePlan[];
    modules: ConsoleModule[];
}

/** Plan catalogue: prices per cycle, included modules, user limit. */
export default function BillingPlans({ plans, modules }: PlansProps) {
    const [notice, setNotice] = useState(false);

    const moduleLabel = (key: string) =>
        modules.find((module) => module.key === key)?.label ?? key;

    return (
        <ProviderLayout>
            <Head title="Paket" />

            <PageHeader
                title="Paket"
                description="Katalog paket yang bisa dipilih sekolah."
            />

            {notice && (
                <div
                    role="status"
                    className="mt-6 rounded-lg border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-foreground"
                >
                    Pengeditan paket belum tersedia (tahap tampilan).
                </div>
            )}

            <div className="mt-8 grid gap-6 lg:grid-cols-3">
                {plans.map((plan) => {
                    const yearlySaving =
                        plan.priceMonthly * 12 - plan.priceYearly;

                    return (
                        <Panel key={plan.key}>
                            <h2 className="text-sm font-semibold text-foreground">
                                {plan.label}
                            </h2>
                            <p className="mt-3 text-xl font-semibold text-foreground">
                                {formatRupiah(plan.priceMonthly)}
                                <span className="text-sm font-normal text-muted-foreground">
                                    {' '}
                                    / bulan
                                </span>
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {formatRupiah(plan.priceYearly)} / tahun
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Hemat {formatRupiah(yearlySaving)} bila tahunan
                            </p>

                            <p className="mt-5 text-xs text-muted-foreground">Batas pengguna</p>
                            <p className="text-sm text-foreground">
                                {plan.maxUsers === null ? 'Tanpa batas' : plan.maxUsers}
                            </p>

                            <p className="mt-4 text-xs text-muted-foreground">Modul</p>
                            <ul className="mt-1 text-sm text-foreground">
                                {plan.modules.map((key) => (
                                    <li key={key}>{moduleLabel(key)}</li>
                                ))}
                            </ul>

                            <p className="mt-4 text-xs text-muted-foreground">
                                {plan.subscribers} sekolah berlangganan
                            </p>

                            <button
                                type="button"
                                className={`${buttonGhost} mt-5 w-full`}
                                onClick={() => setNotice(true)}
                            >
                                Ubah paket
                            </button>
                        </Panel>
                    );
                })}
            </div>
        </ProviderLayout>
    );
}
