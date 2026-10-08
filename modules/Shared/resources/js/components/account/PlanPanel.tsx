import { useState } from 'react';

import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Label } from '@shared/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@shared/components/ui/radio-group';
import { useBilling } from '@shared/hooks/useBilling';
import {
    cycleLabel,
    formatDate,
    formatRupiah,
    limitText,
    stateLabel,
    stateTone,
} from '@shared/lib/billing';
import { cn } from '@shared/lib/utils';
import type {
    BillingCycleKey,
    BillingOverview,
    PlanOffer,
    SchoolBilling,
    UsageLine,
} from '@shared/types/billing';

import { Feedback, PanelSkeleton, Row } from './PanelParts';

type Act = ReturnType<typeof useBilling>['act'];

/**
 * "Paket & Langganan": the school's plan, how it is doing against its
 * limits, and what it may do about it (subscribe, change plan, change the
 * cycle). Everything shown comes from the server; the links the buttons
 * use are in the prop too.
 */
export default function PlanPanel({ active }: { active: boolean }) {
    const { billing, busy, error, notice, act } = useBilling(active);

    if (billing === undefined) {
        return <PanelSkeleton />;
    }

    if (billing === null) {
        return (
            <p className="text-muted-foreground">
                Anda tidak punya izin untuk melihat langganan sekolah.
            </p>
        );
    }

    const { overview } = billing;

    return (
        <div className="flex flex-col gap-5">
            <Summary overview={overview} />
            <Feedback error={error} notice={notice} />

            {overview.state === 'exempt' && (
                <p className="text-muted-foreground">
                    Sekolah ini dibebaskan dari tagihan oleh penyedia layanan.
                </p>
            )}
            {overview.state === 'none' && (
                <p className="text-muted-foreground">
                    Sekolah ini belum punya langganan. Hubungi penyedia layanan.
                </p>
            )}

            <UsageList usage={overview.usage} />

            {overview.modules.length > 0 && (
                <dl className="border-t border-border">
                    <Row label="Modul aktif">
                        {overview.modules
                            .map((module) => module.label)
                            .join(' · ')}
                    </Row>
                </dl>
            )}

            <Actions billing={billing} busy={busy} act={act} />
        </div>
    );
}

function Summary({ overview }: { overview: BillingOverview }) {
    const rows: [string, string][] = [];

    if (
        overview.trialEndsOn &&
        (overview.state === 'trial' || overview.state === 'trial_expired')
    ) {
        rows.push(['Uji coba berakhir', formatDate(overview.trialEndsOn)]);
    }

    if (
        overview.periodStart &&
        overview.periodEnd &&
        ['active', 'due', 'overdue'].includes(overview.state)
    ) {
        rows.push([
            'Periode berjalan',
            `${formatDate(overview.periodStart)} – ${formatDate(overview.periodEnd)}`,
        ]);
        rows.push(['Perpanjangan berikutnya', formatDate(overview.periodEnd)]);
    }

    if (
        overview.accessEndsOn &&
        ['trial_expired', 'overdue', 'cancelled'].includes(overview.state)
    ) {
        rows.push(['Akses sampai', formatDate(overview.accessEndsOn)]);
    }

    if (overview.scheduledPlanName) {
        rows.push([
            'Paket berikutnya',
            `${overview.scheduledPlanName}${overview.periodEnd ? `, berlaku ${formatDate(overview.periodEnd)}` : ''}`,
        ]);
    }

    return (
        <>
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-base font-semibold">
                        {overview.planName ?? 'Belum berlangganan'}
                    </p>
                    {overview.price !== null && overview.cycle !== null && (
                        <p className="text-muted-foreground">
                            {formatRupiah(overview.price)} per{' '}
                            {cycleLabel[overview.cycle]}
                        </p>
                    )}
                </div>
                <Badge variant={stateTone[overview.state]}>
                    {stateLabel[overview.state]}
                </Badge>
            </div>

            {rows.length > 0 && (
                <dl className="border-t border-border">
                    {rows.map(([label, value]) => (
                        <Row key={label} label={label}>
                            {value}
                        </Row>
                    ))}
                </dl>
            )}
        </>
    );
}

function UsageList({ usage }: { usage: UsageLine[] }) {
    if (usage.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-4">
            {usage.map((line) => {
                const percent =
                    line.limit === null || line.limit === 0
                        ? 0
                        : Math.min(
                              100,
                              Math.round((line.used / line.limit) * 100),
                          );

                return (
                    <div key={line.key}>
                        <div className="flex justify-between gap-4 text-sm">
                            <span className="text-muted-foreground">
                                {line.label}
                            </span>
                            <span className="font-medium">
                                {line.used.toLocaleString('id-ID')}
                                {line.limit === null
                                    ? ` ${line.unit} · tanpa batas`
                                    : ` / ${line.limit.toLocaleString('id-ID')} ${line.unit}`}
                            </span>
                        </div>
                        {line.limit !== null && (
                            <div
                                role="progressbar"
                                aria-label={line.label}
                                aria-valuenow={line.used}
                                aria-valuemin={0}
                                aria-valuemax={line.limit}
                                className="mt-1.5 h-1.5 bg-muted"
                            >
                                <div
                                    className={cn(
                                        'h-full',
                                        line.state === 'over' &&
                                            'bg-destructive',
                                        line.state === 'near' && 'bg-accent',
                                        line.state === 'ok' && 'bg-primary',
                                    )}
                                    style={{ width: `${percent}%` }}
                                />
                            </div>
                        )}
                        {line.state === 'over' && (
                            <p className="mt-1 text-xs text-destructive">
                                Melebihi batas paket. Hubungi penyedia layanan
                                atau ganti paket.
                            </p>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

function Actions({
    billing,
    busy,
    act,
}: {
    billing: SchoolBilling;
    busy: boolean;
    act: Act;
}) {
    const { overview, offers, urls } = billing;

    if (!billing.canPay || urls === null) {
        return null;
    }

    return (
        <div className="flex flex-col gap-5 border-t border-border pt-5">
            {overview.canSubscribe && offers.length > 0 && (
                <SubscribeSection
                    offers={offers}
                    url={urls.subscribe}
                    busy={busy}
                    act={act}
                />
            )}

            {overview.canChangePlan &&
                offers.some((offer) => offer.direction !== 'current') && (
                    <ChangePlanSection
                        offers={offers}
                        overview={overview}
                        url={urls.changePlan}
                        busy={busy}
                        act={act}
                    />
                )}

            {overview.scheduledPlanName && (
                <div className="flex items-center justify-between gap-4 text-sm">
                    <span className="text-muted-foreground">
                        Turun ke {overview.scheduledPlanName} sudah dijadwalkan.
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={busy}
                        onClick={() =>
                            act('delete', urls.cancelScheduledChange)
                        }
                    >
                        Batalkan jadwal
                    </Button>
                </div>
            )}

            {overview.cycle !== null &&
                ['active', 'due', 'overdue'].includes(overview.state) && (
                    <CycleSection
                        cycle={overview.cycle}
                        url={urls.changeCycle}
                        busy={busy}
                        act={act}
                    />
                )}
        </div>
    );
}

function PriceLine({ offer }: { offer: PlanOffer }) {
    return (
        <span className="text-sm text-muted-foreground">
            {formatRupiah(offer.priceMonthly)}/bulan ·{' '}
            {formatRupiah(offer.priceYearly)}/tahun
        </span>
    );
}

function OfferFacts({ offer }: { offer: PlanOffer }) {
    return (
        <ul className="mt-1 text-xs text-muted-foreground">
            <li>{limitText(offer.limits.students, 'siswa')}</li>
            <li>{limitText(offer.limits.staffAccounts, 'akun staf')}</li>
            <li>
                Modul: {offer.modules.map((module) => module.label).join(', ')}
            </li>
        </ul>
    );
}

function SubscribeSection({
    offers,
    url,
    busy,
    act,
}: {
    offers: PlanOffer[];
    url: string;
    busy: boolean;
    act: Act;
}) {
    const [plan, setPlan] = useState(offers[0]?.key ?? '');
    const [cycle, setCycle] = useState<BillingCycleKey>('monthly');

    return (
        <section className="flex flex-col gap-3" aria-label="Berlangganan">
            <h3 className="text-sm font-semibold">Berlangganan</h3>

            <RadioGroup value={plan} onValueChange={setPlan} className="gap-2">
                {offers.map((offer) => (
                    <div
                        key={offer.key}
                        className="flex items-start gap-3 border border-border px-3 py-2"
                    >
                        <RadioGroupItem
                            value={offer.key}
                            id={`offer-${offer.key}`}
                            className="mt-1"
                        />
                        <Label
                            htmlFor={`offer-${offer.key}`}
                            className="flex flex-1 flex-col items-start gap-0.5 font-normal"
                        >
                            <span className="font-medium">{offer.name}</span>
                            <PriceLine offer={offer} />
                            <OfferFacts offer={offer} />
                        </Label>
                    </div>
                ))}
            </RadioGroup>

            <RadioGroup
                value={cycle}
                onValueChange={(value) => setCycle(value as BillingCycleKey)}
                className="flex gap-4"
                aria-label="Siklus pembayaran"
            >
                {(['monthly', 'yearly'] as const).map((key) => (
                    <div key={key} className="flex items-center gap-2">
                        <RadioGroupItem value={key} id={`cycle-${key}`} />
                        <Label htmlFor={`cycle-${key}`} className="font-normal">
                            {key === 'monthly' ? 'Bulanan' : 'Tahunan'}
                        </Label>
                    </div>
                ))}
            </RadioGroup>

            <Button
                disabled={busy || plan === ''}
                onClick={() => act('post', url, { plan, cycle })}
            >
                Berlangganan
            </Button>
            <p className="text-xs text-muted-foreground">
                Setelah invoice terbit, bayar lewat menu Tagihan &amp; Invoice.
                Paket aktif setelah pembayaran dikonfirmasi.
            </p>
        </section>
    );
}

function ChangePlanSection({
    offers,
    overview,
    url,
    busy,
    act,
}: {
    offers: PlanOffer[];
    overview: BillingOverview;
    url: string;
    busy: boolean;
    act: Act;
}) {
    const [choice, setChoice] = useState<string | null>(null);
    const others = offers.filter((offer) => offer.direction !== 'current');

    return (
        <section className="flex flex-col gap-3" aria-label="Ganti paket">
            <h3 className="text-sm font-semibold">Ganti paket</h3>

            <ul className="flex flex-col gap-2">
                {others.map((offer) => (
                    <li
                        key={offer.key}
                        className="border border-border px-3 py-2"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div className="flex flex-col">
                                <span className="font-medium">
                                    {offer.name}{' '}
                                    {offer.direction === 'upgrade' && (
                                        <Badge variant="outline">naik</Badge>
                                    )}
                                    {offer.direction === 'downgrade' && (
                                        <Badge variant="outline">turun</Badge>
                                    )}
                                </span>
                                <PriceLine offer={offer} />
                                <OfferFacts offer={offer} />
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={busy}
                                onClick={() =>
                                    setChoice(
                                        choice === offer.key ? null : offer.key,
                                    )
                                }
                            >
                                Pilih
                            </Button>
                        </div>

                        {choice === offer.key && (
                            <div className="mt-3 flex flex-col gap-2 border-t border-border pt-3 text-sm">
                                <p>{consequence(offer, overview)}</p>
                                {offer.modulesLost.length > 0 && (
                                    <p className="text-destructive">
                                        Modul yang tidak lagi tersedia:{' '}
                                        {offer.modulesLost.join(', ')}.
                                    </p>
                                )}
                                {offer.overLimits.length > 0 && (
                                    <p className="text-destructive">
                                        Pemakaian akan melebihi batas:{' '}
                                        {offer.overLimits
                                            .map(
                                                (over) =>
                                                    `${over.label} ${over.used.toLocaleString('id-ID')} dari ${over.limit.toLocaleString('id-ID')} ${over.unit}`,
                                            )
                                            .join('; ')}
                                        .
                                    </p>
                                )}
                                <div className="flex gap-2">
                                    <Button
                                        size="sm"
                                        disabled={busy}
                                        onClick={() =>
                                            act(
                                                'post',
                                                url,
                                                { plan: offer.key },
                                                () => setChoice(null),
                                            )
                                        }
                                    >
                                        Ya, ganti paket
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setChoice(null)}
                                    >
                                        Batal
                                    </Button>
                                </div>
                            </div>
                        )}
                    </li>
                ))}
            </ul>
        </section>
    );
}

/** What choosing this plan will do, in words. */
function consequence(offer: PlanOffer, overview: BillingOverview): string {
    if (overview.state === 'trial' || overview.state === 'trial_expired') {
        return 'Paket berlaku segera selama masa uji coba. Tidak ada tagihan baru.';
    }

    if (offer.direction === 'upgrade') {
        return 'Kami menerbitkan invoice selisih untuk sisa periode. Paket baru aktif setelah invoice dibayar dan dikonfirmasi.';
    }

    return `Paket turun berlaku saat periode berjalan berakhir${overview.periodEnd ? ` (${formatDate(overview.periodEnd)})` : ''}. Sampai saat itu paket sekarang tetap berlaku, tanpa pengembalian dana.`;
}

function CycleSection({
    cycle,
    url,
    busy,
    act,
}: {
    cycle: BillingCycleKey;
    url: string;
    busy: boolean;
    act: Act;
}) {
    const other: BillingCycleKey = cycle === 'monthly' ? 'yearly' : 'monthly';

    return (
        <div className="flex items-center justify-between gap-4 text-sm">
            <span className="text-muted-foreground">
                Siklus pembayaran: {cycle === 'monthly' ? 'bulanan' : 'tahunan'}
            </span>
            <Button
                variant="outline"
                size="sm"
                disabled={busy}
                onClick={() => act('put', url, { cycle: other })}
            >
                Pindah ke {other === 'monthly' ? 'bulanan' : 'tahunan'}
            </Button>
        </div>
    );
}
