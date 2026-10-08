<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Platform\App\Contracts\TenantApplications;
use Modules\Platform\App\Domain\Models\Applicant;
use Modules\Platform\App\Domain\Models\BillingCycle;
use Modules\Platform\App\Domain\Models\BillingNotice;
use Modules\Platform\App\Domain\Models\BillingNoticeKind;
use Modules\Platform\App\Domain\Models\BillingNoticeStatus;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Support\BillingClock;
use Modules\Platform\App\Infrastructure\Billing\BillingNotifier;
use Modules\Platform\App\Infrastructure\Billing\InvoiceDocument;
use Modules\Platform\App\Infrastructure\Billing\InvoiceIssuer;
use Modules\Platform\App\Infrastructure\Billing\SubscriptionManager;
use Modules\Platform\App\Infrastructure\Mail\AccessReopenedMail;
use Modules\Platform\App\Infrastructure\Mail\AccessStoppedMail;
use Modules\Platform\App\Infrastructure\Mail\BillingMail;
use Modules\Platform\App\Infrastructure\Mail\DueReminderMail;
use Modules\Platform\App\Infrastructure\Mail\InvoiceIssuedMail;
use Modules\Platform\App\Infrastructure\Mail\OverdueReminderMail;
use Modules\Platform\App\Infrastructure\Mail\PaymentReceivedMail;
use Modules\Platform\Database\Factories\ApplicantFactory;
use Modules\Platform\Database\Factories\BillingNoticeFactory;
use Modules\Platform\Database\Factories\PlanFactory;
use Modules\Platform\Database\Factories\ProviderUserFactory;
use Modules\Platform\Database\Factories\SubscriptionFactory;
use Modules\Platform\Database\Factories\TenantFactory;
use Modules\Platform\Tests\Support\FakePaymentGateway;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

/**
 * Invoice PDF, the six billing mails, the notice history and the billing
 * contact (fase 17, tahap 5).
 */
function noticeConsole(string $path): string
{
    return 'http://console.localhost'.$path;
}

function noticeProvider(): void
{
    actingAs(ProviderUserFactory::new()->create(), 'provider');
}

/**
 * A trial subscription whose school has a billing contact (or none).
 */
function noticeSubscription(?string $email = 'keuangan@sekolah.test'): Subscription
{
    $subscription = SubscriptionFactory::new()->trialEndingIn(5)->create();

    Tenant::query()->whereKey($subscription->tenant_id)->update([
        'billing_email' => $email,
        'billing_name' => 'Bu Rina',
    ]);

    return $subscription->refresh();
}

function noticeInvoice(?Subscription $subscription = null): Invoice
{
    $subscription ??= noticeSubscription();

    return app(SubscriptionManager::class)->activate($subscription, $subscription->plan->key, BillingCycle::Monthly);
}

function noticePay(Invoice $invoice): void
{
    $gateway = FakePaymentGateway::paying();

    app(SubscriptionManager::class)->settle($gateway->initiate($invoice), $gateway->outcome($invoice));
}

it('queues the invoice email, with the PDF, when an invoice is newly issued', function () {
    Mail::fake();
    $subscription = noticeSubscription();

    $invoice = noticeInvoice($subscription);

    Mail::assertQueued(InvoiceIssuedMail::class, fn (InvoiceIssuedMail $mail): bool => $mail->hasTo('keuangan@sekolah.test')
        && $mail->invoiceNumber === $invoice->number
        && $mail->recipientName === 'Bu Rina'
        && str_starts_with((string) $mail->amount, 'Rp ')
        && count($mail->attachments()) === 1);

    $notice = BillingNotice::query()->sole();

    expect($notice->kind)->toBe(BillingNoticeKind::InvoiceIssued)
        ->and($notice->status)->toBe(BillingNoticeStatus::Queued)
        ->and($notice->recipient)->toBe('keuangan@sekolah.test')
        ->and($notice->invoice_id)->toBe($invoice->id)
        ->and($notice->subscription_id)->toBe($subscription->id)
        ->and($notice->tenant_id)->toBe($subscription->tenant_id);
});

it('does not announce an invoice that was reused', function () {
    Mail::fake();
    $subscription = noticeSubscription();

    $first = noticeInvoice($subscription);
    $second = noticeInvoice($subscription);

    expect($second->id)->toBe($first->id)
        ->and(BillingNotice::query()->count())->toBe(1);
    Mail::assertQueuedCount(1);
});

it('queues the receipt, with the PDF, only the first time an invoice is settled', function () {
    $invoice = noticeInvoice();
    Mail::fake();

    noticePay($invoice);
    noticePay($invoice);

    Mail::assertQueued(PaymentReceivedMail::class, 1);
    Mail::assertQueued(PaymentReceivedMail::class, fn (PaymentReceivedMail $mail): bool => $mail->hasTo('keuangan@sekolah.test')
        && $mail->invoiceNumber === $invoice->number
        && count($mail->attachments()) === 1);
    expect(BillingNotice::query()->where('kind', BillingNoticeKind::PaymentReceived)->count())->toBe(1);
});

it('queues each of the remaining mails to the billing contact with the right values', function () {
    $subscription = noticeSubscription();
    $invoice = noticeInvoice($subscription);
    Mail::fake();
    $notifier = app(BillingNotifier::class);
    $anchor = BillingClock::today();
    $graceEnd = $anchor->copy()->addDays(7);

    $notifier->dueReminder($invoice, $anchor);
    $notifier->overdueReminder($invoice, $anchor, $graceEnd);
    $notifier->accessStopped($subscription, $invoice, $anchor);
    $notifier->accessReopened($subscription, $invoice);

    Mail::assertQueued(DueReminderMail::class, fn (DueReminderMail $mail): bool => $mail->hasTo('keuangan@sekolah.test')
        && $mail->invoiceNumber === $invoice->number
        && $mail->dueOn === $invoice->due_at->format('d/m/Y')
        && $mail->attachments() === []);
    Mail::assertQueued(OverdueReminderMail::class, fn (OverdueReminderMail $mail): bool => $mail->hasTo('keuangan@sekolah.test')
        && $mail->graceEndsOn === $graceEnd->format('d/m/Y'));
    Mail::assertQueued(AccessStoppedMail::class, fn (AccessStoppedMail $mail): bool => $mail->hasTo('keuangan@sekolah.test')
        && $mail->schoolName === $subscription->tenant->name);
    Mail::assertQueued(AccessReopenedMail::class, fn (AccessReopenedMail $mail): bool => $mail->hasTo('keuangan@sekolah.test'));

    expect(BillingNotice::query()->pluck('kind')->map->value->sort()->values()->all())->toBe([
        'access_reopened', 'access_stopped', 'due_reminder', 'invoice_issued', 'overdue_reminder',
    ]);
});

it('renders every mail view in Indonesian with the invoice values', function (string $mailClass) {
    /** @var BillingMail $mail */
    $mail = new $mailClass('Bu Rina', 'SMA Uji', 1, 'SIMAS', 'tagihan@simas.test', null, 'INV-3001-0001', 'Rp 150.000', '01/01/2030', '01/01/2030 - 01/02/2030', '08/01/2030');

    $body = $mail->render();

    expect($mail->envelope()->subject)->not->toBeEmpty()
        ->and($body)->toContain('Halo Bu Rina')
        ->and($body)->toContain('SMA Uji')
        ->and($body)->toContain('— SIMAS');
})->with([
    InvoiceIssuedMail::class,
    DueReminderMail::class,
    OverdueReminderMail::class,
    PaymentReceivedMail::class,
    AccessStoppedMail::class,
    AccessReopenedMail::class,
]);

it('records a failed notice, queues nothing and throws nothing when the school has no billing email', function () {
    Mail::fake();

    $invoice = noticeInvoice(noticeSubscription(null));

    $notice = BillingNotice::query()->sole();

    expect($invoice->status)->toBe(InvoiceStatus::Unpaid)
        ->and($notice->status)->toBe(BillingNoticeStatus::Failed)
        ->and($notice->recipient)->toBeNull()
        ->and($notice->error)->toBe(BillingNotifier::NO_CONTACT);
    Mail::assertNothingQueued();

    noticePay($invoice);

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Paid)
        ->and(BillingNotice::query()->where('kind', BillingNoticeKind::PaymentReceived)->sole()->status)->toBe(BillingNoticeStatus::Failed);
    Mail::assertNothingQueued();
});

it('records a scheduled notice once per subscription, kind and anchor date', function () {
    Mail::fake();
    $invoice = noticeInvoice();
    $notifier = app(BillingNotifier::class);
    $anchor = Carbon::parse('2030-03-10');

    $first = $notifier->dueReminder($invoice, $anchor);
    $again = $notifier->dueReminder($invoice, $anchor);
    $nextDay = $notifier->dueReminder($invoice, $anchor->copy()->addDay());

    expect($first)->not->toBeNull()
        ->and($again)->toBeNull()
        ->and($nextDay)->not->toBeNull()
        ->and(BillingNotice::query()->where('kind', BillingNoticeKind::DueReminder)->count())->toBe(2);
    Mail::assertQueued(DueReminderMail::class, 2);

    expect(fn () => BillingNoticeFactory::new()->create([
        'subscription_id' => $invoice->subscription_id,
        'kind' => BillingNoticeKind::DueReminder,
        'anchor_date' => '2030-03-10',
    ]))->toThrow(QueryException::class);
});

it('marks a notice sent and failed from the mail lifecycle', function () {
    $notice = BillingNoticeFactory::new()->create();
    $mail = new InvoiceIssuedMail('Bu Rina', 'SMA Uji', $notice->id, 'SIMAS');

    BillingNotifier::markSent($notice->id);

    expect($notice->refresh()->status)->toBe(BillingNoticeStatus::Sent)
        ->and($notice->sent_at)->not->toBeNull();

    $other = BillingNoticeFactory::new()->create();
    (new InvoiceIssuedMail('Bu Rina', 'SMA Uji', $other->id, 'SIMAS'))->failed(new RuntimeException('smtp down'));

    expect($other->refresh()->status)->toBe(BillingNoticeStatus::Failed)
        ->and($other->error)->toBe('smtp down')
        ->and($mail->headers()->text[BillingMail::NOTICE_HEADER])->toBe((string) $notice->id);
});

it('numbers invoices in sequence without repeats', function () {
    Mail::fake();
    $issuer = app(InvoiceIssuer::class);

    $numbers = collect(range(1, 5))->map(function () use ($issuer): string {
        $subscription = SubscriptionFactory::new()->trialEndingIn(5)->create();

        return $issuer->issueActivation($subscription, $subscription->plan, BillingCycle::Monthly, BillingClock::today())->number;
    });

    $prefix = 'INV-'.BillingClock::today()->format('ym').'-';

    expect($numbers->unique())->toHaveCount(5)
        ->and($numbers->all())->toBe(collect(range(1, 5))->map(fn (int $n): string => $prefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT))->all());
});

it('renders a real PDF carrying the invoice number, amount and status stamp', function () {
    $invoice = noticeInvoice();
    $document = app(InvoiceDocument::class);

    $pdf = $document->render($invoice);

    expect($pdf)->toStartWith('%PDF')
        ->and($document->stamp($invoice))->toBe('Belum dibayar');

    config(['billing.issuer.name' => 'PT Penerbit Uji', 'billing.issuer.bank' => ['name' => 'BCA', 'account' => '1234567890', 'holder' => 'PT Penerbit Uji']]);
    $html = view('Platform::invoice-pdf', [
        'invoice' => $invoice,
        'schoolName' => $invoice->tenant->name,
        'contactName' => 'Bu Rina',
        'contactEmail' => 'keuangan@sekolah.test',
        'issuer' => config('billing.issuer'),
        'stamp' => $document->stamp($invoice),
    ])->render();

    expect($html)->toContain($invoice->number)
        ->and($html)->toContain('Rp '.number_format($invoice->amount, 0, ',', '.'))
        ->and($html)->toContain('Belum dibayar')
        ->and($html)->toContain('PT Penerbit Uji')
        ->and($html)->toContain('1234567890')
        ->and($html)->toContain($invoice->plan_name);

    noticePay($invoice);

    expect($document->stamp($invoice->refresh()))->toBe('Lunas');
});

it('gives the provider the PDF download and keeps it behind the provider guard', function () {
    $invoice = noticeInvoice();

    get(noticeConsole("/billing/invoices/{$invoice->id}/pdf"))->assertRedirect();
    post(noticeConsole("/billing/invoices/{$invoice->id}/resend"))->assertRedirect();

    noticeProvider();

    $response = get(noticeConsole("/billing/invoices/{$invoice->id}/pdf"))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain($invoice->number)
        ->and($response->getContent())->toStartWith('%PDF');
});

it('resends the invoice mail for an unpaid invoice and the receipt for a paid one', function () {
    $invoice = noticeInvoice();
    noticeProvider();
    Mail::fake();

    post(noticeConsole("/billing/invoices/{$invoice->id}/resend"))->assertSessionHas('status');
    Mail::assertQueued(InvoiceIssuedMail::class, 1);

    noticePay($invoice);
    Mail::fake();

    post(noticeConsole("/billing/invoices/{$invoice->id}/resend"))->assertSessionHas('status');
    Mail::assertQueued(PaymentReceivedMail::class, 1);
    expect(BillingNotice::query()->where('invoice_id', $invoice->id)->count())->toBe(4);
});

it('refuses to resend a void invoice or to a school without a billing email', function () {
    $void = noticeInvoice();
    app(InvoiceIssuer::class)->void($void);
    $noContact = noticeInvoice(noticeSubscription(null));
    noticeProvider();
    Mail::fake();

    post(noticeConsole("/billing/invoices/{$void->id}/resend"))->assertSessionHasErrors('billing');
    post(noticeConsole("/billing/invoices/{$noContact->id}/resend"))->assertSessionHasErrors('billing');

    Mail::assertNothingQueued();
});

it('shows the notice history on the invoice list', function () {
    $invoice = noticeInvoice();
    noticeProvider();

    get(noticeConsole('/billing/invoices'))->assertInertia(fn (Assert $page) => $page
        ->component('Platform/Billing/Invoices')
        ->where('invoices.data.0.notices.0.kind', 'invoice_issued')
        ->where('invoices.data.0.notices.0.recipient', 'keuangan@sekolah.test')
        ->where('invoices.data.0.id', $invoice->id));
});

it('sets the billing contact from the applicant when an application is approved', function () {
    PlanFactory::new()->create(['key' => 'starter']);
    $applications = app(TenantApplications::class);
    $applicant = ApplicantFactory::new()->create(['name' => 'Pak Budi', 'email' => 'Budi@Sekolah.TEST']);

    $applications->submit($applicant->id, ['school_name' => 'SMA Kontak', 'desired_slug' => 'sma-kontak']);
    $applications->approve($applications->pending()[0]->id, ProviderUserFactory::new()->create()->id);

    $tenant = Tenant::query()->where('slug', 'sma-kontak')->sole();

    expect($tenant->billing_email)->toBe('budi@sekolah.test')
        ->and($tenant->billing_name)->toBe('Pak Budi');
});

it('backfills the billing contact of existing schools from their latest application', function () {
    $linked = TenantFactory::new()->create(['slug' => 'sekolah-terhubung']);
    $bySlug = TenantFactory::new()->create(['slug' => 'sekolah-lama']);
    $kept = TenantFactory::new()->create(['slug' => 'sekolah-isi', 'billing_email' => 'sudah@ada.test', 'billing_name' => 'Sudah Ada']);
    $none = TenantFactory::new()->create(['slug' => 'tanpa-aplikasi']);
    $applicant = ApplicantFactory::new()->create();
    Applicant::query()->whereKey($applicant->id)->update(['tenant_id' => $linked->id]);

    $application = fn (array $overrides): array => array_merge([
        'school_name' => 'X', 'timezone' => 'Asia/Jakarta', 'status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides);

    DB::table('tenant_applications')->insert([
        $application(['applicant_id' => $applicant->id, 'desired_slug' => 'beda-kode', 'applicant_name' => 'Pemohon A', 'applicant_email' => 'A@Satu.test']),
        $application(['applicant_id' => null, 'desired_slug' => 'sekolah-lama', 'applicant_name' => 'Pemohon B', 'applicant_email' => 'b@dua.test']),
        $application(['applicant_id' => null, 'desired_slug' => 'sekolah-isi', 'applicant_name' => 'Pemohon C', 'applicant_email' => 'c@tiga.test']),
        $application(['applicant_id' => null, 'desired_slug' => 'ditolak', 'applicant_name' => 'Pemohon D', 'applicant_email' => 'd@empat.test', 'status' => 'rejected']),
    ]);

    (require base_path('modules/Platform/database/migrations/0010_01_01_000005_backfill_tenant_billing_contact.php'))->up();

    expect($linked->refresh()->billing_email)->toBe('a@satu.test')
        ->and($linked->billing_name)->toBe('Pemohon A')
        ->and($bySlug->refresh()->billing_email)->toBe('b@dua.test')
        ->and($kept->refresh()->billing_email)->toBe('sudah@ada.test')
        ->and($kept->billing_name)->toBe('Sudah Ada')
        ->and($none->refresh()->billing_email)->toBeNull();
});

it('lets the provider edit the billing contact and validates the email', function () {
    noticeProvider();
    $tenant = TenantFactory::new()->create();
    $profile = ['name' => $tenant->name, 'timezone' => 'Asia/Jakarta'];

    put(noticeConsole("/tenants/{$tenant->id}"), $profile + ['billing_email' => ' Keuangan@Sekolah.TEST ', 'billing_name' => 'Bu Rina'])
        ->assertSessionDoesntHaveErrors();

    expect($tenant->refresh()->billing_email)->toBe('keuangan@sekolah.test')
        ->and($tenant->billing_name)->toBe('Bu Rina');

    put(noticeConsole("/tenants/{$tenant->id}"), $profile + ['billing_email' => 'bukan-email'])
        ->assertSessionHasErrors('billing_email');
    expect($tenant->refresh()->billing_email)->toBe('keuangan@sekolah.test');

    put(noticeConsole("/tenants/{$tenant->id}"), $profile)->assertSessionDoesntHaveErrors();
    expect($tenant->refresh()->billing_email)->toBe('keuangan@sekolah.test');

    put(noticeConsole("/tenants/{$tenant->id}"), $profile + ['billing_email' => '', 'billing_name' => ''])
        ->assertSessionDoesntHaveErrors();
    expect($tenant->refresh()->billing_email)->toBeNull();
});

it('shows the billing contact on the tenant page', function () {
    noticeProvider();
    $tenant = TenantFactory::new()->create(['billing_email' => 'keuangan@sekolah.test', 'billing_name' => 'Bu Rina']);

    get(noticeConsole("/tenants/{$tenant->id}"))->assertInertia(fn (Assert $page) => $page
        ->where('tenant.billingEmail', 'keuangan@sekolah.test')
        ->where('tenant.billingName', 'Bu Rina'));
});
