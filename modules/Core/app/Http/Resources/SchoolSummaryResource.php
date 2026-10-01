<?php

namespace Modules\Core\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\App\Domain\Enums\SchoolLevel;
use Modules\Core\App\Domain\Models\SchoolProfile;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * The school summary every master page shares: Platform's name, code and
 * timezone plus Core's own profile record.
 *
 * @mixin SchoolProfile
 */
final class SchoolSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tenant = app(TenantContext::class)->currentOrFail();

        return [
            'name' => $tenant->name,
            'code' => $tenant->slug,
            'timezone' => $tenant->timezone,
            'npsn' => $this->npsn ?? '',
            'level' => $this->level->value,
            'levelLabel' => $this->level->label(),
            'levelOptions' => SchoolLevel::options(),
            'ownership' => $this->ownership ?? '',
            'accreditation' => $this->accreditation ?? '',
            'address' => $this->address ?? '',
            'phone' => $this->phone ?? '',
            'email' => $this->email ?? '',
            'headmaster' => $this->headmaster ?? '',
            'headmasterNip' => $this->headmaster_nip ?? '',
            'hasMajors' => $this->level->hasMajors(),
            'homeroomLabel' => $this->level->homeroomLabel(),
        ];
    }
}
