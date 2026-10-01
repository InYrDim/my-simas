<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Platform\App\Contracts\Events\TenantCreated;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantData;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Models\TenantStatus;
use Modules\Platform\App\Infrastructure\Tenancy\SchoolCodeTenantResolver;
use Modules\Platform\App\Infrastructure\Tenancy\TenantMissingException;
use Modules\Platform\Database\Factories\TenantFactory;

function resolver(): SchoolCodeTenantResolver
{
    return app(SchoolCodeTenantResolver::class);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function createTenant(array $attributes = []): Tenant
{
    $attributes['status'] = $attributes['status']
        ?? TenantStatus::Active->value;

    /** @var Tenant $tenant */
    $tenant = TenantFactory::new()->create($attributes);

    return $tenant;
}

it('resolves a tenant by its school code (the tenant id)', function () {
    $tenant = createTenant(['slug' => 'sekolah-a']);

    $resolved = resolver()->resolve($tenant->id);

    expect($resolved)->toBeInstanceOf(TenantData::class)
        ->and($resolved->id)->toBe($tenant->id)
        ->and($resolved->slug)->toBe('sekolah-a');
});

it('normalizes case and surrounding whitespace in the code', function () {
    $tenant = createTenant(['slug' => 'sekolah-a']);

    expect(resolver()->resolve('  '.strtoupper($tenant->id).' ')->id)->toBe($tenant->id);
});

it('throws for codes that match no tenant', function () {
    resolver()->resolve('01ARZ3NDEKTSV4RRFFQ69G5FAV');
})->throws(TenantMissingException::class);

it('throws for codes that cannot be a tenant id without querying', function () {
    DB::enableQueryLog();

    expect(fn () => resolver()->resolve('sekolah-a'))->toThrow(TenantMissingException::class)
        ->and(DB::getQueryLog())->toBe([]);
});

it('does not resolve by slug or custom domain any more', function () {
    createTenant(['slug' => 'sekolah-a', 'domain' => 'sekolah-a.sch.id']);

    expect(fn () => resolver()->resolve('sekolah-a'))->toThrow(TenantMissingException::class)
        ->and(fn () => resolver()->resolve('sekolah-a.sch.id'))->toThrow(TenantMissingException::class);
});

it('resolves suspended tenants so the middleware can return 403', function () {
    $tenant = createTenant(['slug' => 'sekolah-b', 'status' => 'suspended']);

    $resolved = resolver()->resolve($tenant->id);

    expect($resolved->id)->toBe($tenant->id)
        ->and($resolved->status->value)->toBe('suspended');
});

it('validates slug format, reserved slugs, and timezone', function () {
    $reserved = config('tenancy.reserved_slugs');
    $timezones = timezone_identifiers_list();

    $data = ['slug' => 'admin', 'timezone' => 'Asia/Jakarta'];
    $rules = [
        'slug' => ['required', 'string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'not_in:'.implode(',', $reserved), 'unique:tenants,slug'],
        'timezone' => ['required', 'string', Rule::in($timezones)],
    ];

    expect(Validator::make($data, $rules)->passes())->toBeFalse();

    $data = ['slug' => 'sekolah-c', 'timezone' => 'Not/AZone'];
    expect(Validator::make($data, $rules)->passes())->toBeFalse();

    $data = ['slug' => 'sekolah-c', 'timezone' => 'Asia/Makassar'];
    expect(Validator::make($data, $rules)->passes())->toBeTrue();
});

it('creates tenants through the model and dispatches TenantCreated', function () {
    Event::fake([TenantCreated::class]);

    $tenant = createTenant(['slug' => 'sekolah-d']);

    Event::assertDispatched(
        TenantCreated::class,
        fn ($event): bool => $event->tenantId === $tenant->id,
    );
});

it('exposes tenant data through the context run helper', function () {
    $tenant = createTenant(['slug' => 'sekolah-e']);

    /** @var TenantContext $context */
    $context = app(TenantContext::class);

    $result = $context->run($tenant->id, fn (): ?TenantData => $context->current());

    expect($result)->toBeInstanceOf(TenantData::class)
        ->and($result->id)->toBe($tenant->id)
        ->and($context->current())->toBeNull();
});

it('hydrates the full tenant dto including timezone', function () {
    $tenant = createTenant(['slug' => 'sekolah-f', 'timezone' => 'Asia/Makassar']);

    /** @var TenantContext $context */
    $context = app(TenantContext::class);

    $context->run($tenant->id, function () use ($context, $tenant): void {
        expect($context->currentOrFail()->timezone)->toBe('Asia/Makassar')
            ->and($context->timezone())->toBe($tenant->timezone);
    });

    expect($context->timezone())->toBe(config('tenancy.default_timezone'));
});
