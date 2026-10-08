<?php

use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\Payment;
use Modules\Platform\Database\Factories\InvoiceFactory;
use Modules\Platform\Database\Factories\PlanFactory;
use Modules\Platform\Database\Factories\SubscriptionFactory;

require_once __DIR__.'/Support/school.php';

/**
 * The account panels "Paket & Langganan" and "Tagihan & Invoice" in a real
 * browser: they read the school's subscription from the server and act
 * through it. The rules (isolation between schools, permissions, what a
 * refusal says) are covered by the Platform feature tests; these tests
 * prove the dialogs load their data, show it, and post their forms.
 */
it('shows the school\'s plan, price, usage and modules in the plan panel', function () {
    config(['billing.issuer.bank' => ['name' => 'BCA', 'account' => '1234567890', 'holder' => 'PT SIMAS']]);

    [$page, $tenant] = schoolMemberSignsIn();

    $plan = PlanFactory::new()->create([
        'key' => 'starter',
        'name' => 'Paket Contoh',
        'price_monthly' => 150_000,
        'price_yearly' => 1_500_000,
        'limits' => ['students' => 300, 'staff_accounts' => 25],
    ]);
    SubscriptionFactory::new()->forTenant($tenant->id)->forPlan($plan->id)->active(20)->create();

    $page->click('text=admin-sekolah@sekolah-uji.test')
        ->click('internal:role=menuitem[name="Paket & Langganan"i]')
        ->assertSee('Paket Contoh')
        ->assertSee('Rp 150.000 per bulan')
        ->assertSee('Berlangganan')
        ->assertSee('Siswa')
        ->assertSee('Modul aktif')
        ->assertDontSee('Tampilan contoh')
        ->assertNoJavaScriptErrors();
});

it('lets a trial school pick a plan and a cycle, and gets an invoice', function () {
    [$page, $tenant] = schoolMemberSignsIn();

    PlanFactory::new()->create(['key' => 'starter', 'name' => 'Paket Contoh', 'price_monthly' => 150_000, 'price_yearly' => 1_500_000]);
    SubscriptionFactory::new()->forTenant($tenant->id)->trialEndingIn(5)->create();

    $page->click('text=admin-sekolah@sekolah-uji.test')
        ->click('internal:role=menuitem[name="Paket & Langganan"i]')
        ->assertSee('Uji coba')
        ->click('internal:role=radio[name="Tahunan"i]')
        ->click('internal:role=button[name="Berlangganan"s]')
        ->assertSee('diterbitkan')
        ->assertNoJavaScriptErrors();

    $invoice = Invoice::query()->where('tenant_id', $tenant->id)->sole();

    expect($invoice->amount)->toBe(1_500_000)
        ->and($invoice->billing_cycle->value)->toBe('yearly');
});

it('shows how to pay an invoice and takes a report of the transfer', function () {
    config(['billing.issuer.bank' => ['name' => 'Bank Contoh', 'account' => '9876543210', 'holder' => 'PT SIMAS']]);

    [$page, $tenant] = schoolMemberSignsIn();

    $subscription = SubscriptionFactory::new()->forTenant($tenant->id)->active(20)->create();
    $invoice = InvoiceFactory::new()->create([
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
        'plan_id' => $subscription->plan_id,
        'number' => 'INV-2610-0001',
        'amount' => 250_000,
    ]);

    $page->click('text=admin-sekolah@sekolah-uji.test')
        ->click('internal:role=menuitem[name="Tagihan & Invoice"i]')
        ->assertSee('INV-2610-0001')
        ->assertSee('Belum dibayar')
        ->click('internal:role=button[name="Bayar"s]')
        ->assertSee('Cara membayar')
        ->assertSee('9876543210')
        ->assertSee('PT SIMAS')
        ->click('internal:role=button[name="Saya sudah transfer"i]')
        ->fill('internal:role=textbox[name="Bank pengirim"i]', 'BRI')
        ->fill('internal:role=textbox[name="Nama pengirim"i]', 'Budi Santoso')
        ->fill('internal:role=textbox[name="Nomor referensi (bila ada)"i]', 'TRF-55')
        ->click('internal:role=button[name="Kirim laporan"i]')
        ->assertSee('Laporan transfer terkirim')
        ->assertSee('Budi Santoso')
        ->assertNoJavaScriptErrors();

    $payment = Payment::query()->where('invoice_id', $invoice->id)->sole();

    expect($payment->meta['reported']['senderName'])->toBe('Budi Santoso')
        ->and($payment->meta['reported']['reference'])->toBe('TRF-55')
        ->and($invoice->refresh()->status->value)->toBe('unpaid');
});

it('does not offer the billing panels to a teacher', function () {
    [$page] = schoolMemberSignsIn('guru');

    $page->click('text=guru@sekolah-uji.test')
        ->assertSee('Pusat Bantuan')
        ->assertDontSee('Paket & Langganan')
        ->assertDontSee('Tagihan & Invoice')
        ->assertNoJavaScriptErrors();
});
