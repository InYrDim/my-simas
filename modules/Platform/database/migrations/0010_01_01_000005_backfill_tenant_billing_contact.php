<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Schools approved before the billing contact existed get it from
     * their latest approved application: found through the applicant that
     * submitted it (applicants.tenant_id), or, for applications from before
     * applicant accounts existed, through the school code the application
     * asked for. A contact already filled in is kept; a school without an
     * application stays without one.
     */
    public function up(): void
    {
        $applications = DB::table('tenant_applications')
            ->where('status', 'approved')
            ->orderBy('id')
            ->get();

        foreach ($applications as $application) {
            $tenantId = null;

            if ($application->applicant_id !== null) {
                $tenantId = DB::table('applicants')->where('id', $application->applicant_id)->value('tenant_id');
            }

            $tenantId ??= DB::table('tenants')->where('slug', $application->desired_slug)->value('id');

            if ($tenantId === null) {
                continue;
            }

            DB::table('tenants')
                ->where('id', $tenantId)
                ->whereNull('billing_email')
                ->update([
                    'billing_email' => mb_strtolower((string) $application->applicant_email),
                    'billing_name' => $application->applicant_name,
                ]);
        }
    }

    public function down(): void
    {
        // The contact is data, not structure: nothing to undo.
    }
};
