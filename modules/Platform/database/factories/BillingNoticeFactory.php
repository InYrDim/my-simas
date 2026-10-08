<?php

namespace Modules\Platform\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Platform\App\Domain\Models\BillingNotice;
use Modules\Platform\App\Domain\Models\BillingNoticeKind;
use Modules\Platform\App\Domain\Models\BillingNoticeStatus;

/**
 * A queued invoice-issued email. subscription_id and invoice_id come from
 * the caller.
 *
 * @extends Factory<BillingNotice>
 */
class BillingNoticeFactory extends Factory
{
    protected $model = BillingNotice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'subscription_id' => null,
            'invoice_id' => null,
            'kind' => BillingNoticeKind::InvoiceIssued,
            'channel' => 'email',
            'recipient' => fake()->safeEmail(),
            'status' => BillingNoticeStatus::Queued,
            'anchor_date' => null,
        ];
    }
}
