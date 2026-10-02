<?php

namespace Modules\Platform\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Platform\App\Domain\Models\WhatsappInstance;
use Modules\Platform\App\Domain\Models\WhatsappInstanceStatus;

/**
 * Defaults to a request waiting for the provider. `active()` adds the
 * OpenWA session and its key; storing a key needs
 * `services.openwa.credentials_key` to be set.
 *
 * @extends Factory<WhatsappInstance>
 */
class WhatsappInstanceFactory extends Factory
{
    protected $model = WhatsappInstance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'status' => WhatsappInstanceStatus::Pending,
            'requested_at' => now(),
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenantId]);
    }

    /**
     * Waiting for the provider (the default).
     */
    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => WhatsappInstanceStatus::Pending]);
    }

    /**
     * Approved, with a session that has not been linked to a number yet.
     */
    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => WhatsappInstanceStatus::Active,
            'decided_at' => now(),
            'session_id' => (string) Str::uuid(),
            'session_name' => 'simas-'.Str::lower((string) Str::ulid()),
            'api_key' => 'owa_k1_'.bin2hex(random_bytes(32)),
            'api_key_id' => (string) Str::uuid(),
            'connection_status' => 'created',
        ]);
    }

    /**
     * Approved and linked to a WhatsApp number.
     */
    public function connected(string $phone = '6281234567890'): static
    {
        return $this->active()->state(fn (): array => [
            'connection_status' => 'ready',
            'phone' => $phone,
            'push_name' => 'TU Sekolah',
            'connected_at' => now(),
        ]);
    }

    public function rejected(string $note = 'Data sekolah belum lengkap.'): static
    {
        return $this->state(fn (): array => [
            'status' => WhatsappInstanceStatus::Rejected,
            'decided_at' => now(),
            'note' => $note,
        ]);
    }

    public function disabled(string $note = 'Dinonaktifkan oleh provider.'): static
    {
        return $this->active()->state(fn (): array => [
            'status' => WhatsappInstanceStatus::Disabled,
            'note' => $note,
            'connection_status' => 'disconnected',
        ]);
    }
}
