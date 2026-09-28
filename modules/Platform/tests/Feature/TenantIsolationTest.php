<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;
use Modules\Platform\App\Contracts\Exceptions\TenantNotSetException;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Infrastructure\Tenancy\TenantHydrator;

/**
 * Test-only tenant-scoped model (table created per-test via Schema).
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string $code
 */
class TestThing extends Model
{
    use BelongsToTenant;

    protected $table = 'test_things';

    protected $fillable = ['name', 'code', 'tenant_id'];

    public $timestamps = false;
}

beforeEach(function () {
    Schema::dropIfExists('test_things');
    Schema::create('test_things', function (Blueprint $table) {
        $table->id();
        $table->foreignUlid('tenant_id')->constrained('tenants');
        $table->index('tenant_id');
        $table->string('name');
        $table->string('code');
        $table->timestamps();

        $table->unique(['tenant_id', 'code']);
    });
});

afterEach(function () {
    Schema::dropIfExists('test_things');
    app(TenantContext::class)->forget();
});

function tenantIdFor(string $slug): string
{
    $tenant = Tenant::query()->create(['name' => ucfirst($slug), 'slug' => $slug]);

    return $tenant->id;
}

it('isolates queries between tenants (get/first/count)', function () {
    $a = tenantIdFor('sekolah-a');
    $b = tenantIdFor('sekolah-b');

    $context = app(TenantContext::class);

    $context->run($a, function (): void {
        TestThing::query()->create(['name' => 'A-thing', 'code' => 'X1']);
    });

    $context->run($b, function (): void {
        TestThing::query()->create(['name' => 'B-thing', 'code' => 'X1']);
    });

    $context->run($a, function (): void {
        expect(TestThing::query()->count())->toBe(1)
            ->and(TestThing::query()->first()?->name)->toBe('A-thing')
            ->and(TestThing::query()->pluck('name')->all())->toBe(['A-thing']);
    });

    $context->run($b, function (): void {
        expect(TestThing::query()->count())->toBe(1)
            ->and(TestThing::query()->first()?->name)->toBe('B-thing');
    });
});

it('finds by id only within the current tenant', function () {
    $a = tenantIdFor('sekolah-a');
    $b = tenantIdFor('sekolah-b');

    $context = app(TenantContext::class);

    $thingInA = $context->run($a, fn (): TestThing => TestThing::query()->create(['name' => 'A-thing', 'code' => 'X1']));

    $context->run($b, function () use ($thingInA): void {
        expect(TestThing::query()->find($thingInA->id))->toBeNull();
    });

    $context->run($a, fn () => expect(TestThing::query()->find($thingInA->id))->not->toBeNull());
});

it('auto-fills tenant_id on create', function () {
    $a = tenantIdFor('sekolah-a');

    $thing = app(TenantContext::class)->run($a, fn (): TestThing => TestThing::query()->create(['name' => 'X', 'code' => 'C1']));

    expect($thing->tenant_id)->toBe($a);
});

it('throws when creating without tenant context', function () {
    tenantIdFor('sekolah-a');

    TestThing::query()->create(['name' => 'X', 'code' => 'C1']);
})->throws(TenantNotSetException::class);

it('queries fail closed without tenant context', function () {
    tenantIdFor('sekolah-a');

    TestThing::query()->get();
})->throws(TenantNotSetException::class);

it('runWithoutTenant sees everything and restores context', function () {
    $a = tenantIdFor('sekolah-a');
    $b = tenantIdFor('sekolah-b');
    $context = app(TenantContext::class);

    $context->run($a, fn (): TestThing => TestThing::query()->create(['name' => 'A', 'code' => '1']));
    $context->run($b, fn (): TestThing => TestThing::query()->create(['name' => 'B', 'code' => '1']));

    $count = $context->runWithoutTenant(fn (): int => TestThing::query()->count());

    expect($count)->toBe(2)
        ->and($context->id())->toBeNull();

    $context->run($a, function () use ($context): void {
        $crossCount = $context->runWithoutTenant(fn (): int => TestThing::query()->where('name', 'B')->count());

        expect($crossCount)->toBe(1)
            ->and($context->id())->not->toBeNull();
    });
});

it('prevents changing tenant_id of an existing row', function () {
    $a = tenantIdFor('sekolah-a');
    $b = tenantIdFor('sekolah-b');
    $context = app(TenantContext::class);

    $thing = $context->run($a, fn (): TestThing => TestThing::query()->create(['name' => 'A', 'code' => '1']));

    $context->run($b, function () use ($context, $thing, $b): void {
        // The scope hides the row from tenant B entirely...
        expect(TestThing::query()->whereKey($thing->id)->exists())->toBeFalse();

        // ...and even a forced update must not move tenants.
        $context->runWithoutTenant(function () use ($thing, $b): void {
            $thing->tenant_id = $b;
            $thing->save();

            expect($thing->refresh()->tenant_id)->not->toBe($b);
        });
    });
});

it('scopes bulk update and delete to the current tenant', function () {
    $a = tenantIdFor('sekolah-a');
    $b = tenantIdFor('sekolah-b');
    $context = app(TenantContext::class);

    $context->run($a, function (): void {
        TestThing::query()->create(['name' => 'A1', 'code' => '1']);
        TestThing::query()->create(['name' => 'A2', 'code' => '2']);
    });

    $context->run($b, function (): void {
        TestThing::query()->create(['name' => 'B1', 'code' => '1']);
    });

    $context->run($a, function (): void {
        TestThing::query()->where('code', '1')->update(['name' => 'renamed']);
        TestThing::query()->where('code', '2')->delete();

        expect(TestThing::query()->count())->toBe(1)
            ->and(TestThing::query()->first()?->name)->toBe('renamed');
    });

    $context->run($b, function (): void {
        expect(TestThing::query()->count())->toBe(1)
            ->and(TestThing::query()->first()?->name)->toBe('B1');
    });
});

it('allows the same unique key in different tenants but not twice in one', function () {
    $a = tenantIdFor('sekolah-a');
    $b = tenantIdFor('sekolah-b');
    $context = app(TenantContext::class);

    $context->run($a, fn (): TestThing => TestThing::query()->create(['name' => 'A', 'code' => 'DUP']));
    $context->run($b, fn (): TestThing => TestThing::query()->create(['name' => 'B', 'code' => 'DUP']));

    // Same code in another tenant: fine (unique is composite tenant_id+code).
    expect(DB::table('test_things')->count())->toBe(2);

    $context->run($a, function (): void {
        TestThing::query()->create(['name' => 'A2', 'code' => 'DUP']);
    });
})->throws(UniqueConstraintViolationException::class);

it('does not protect raw DB::table queries (documented trap)', function () {
    $a = tenantIdFor('sekolah-a');
    $b = tenantIdFor('sekolah-b');
    $context = app(TenantContext::class);

    $context->run($a, fn (): TestThing => TestThing::query()->create(['name' => 'A', 'code' => '1']));
    $context->run($b, fn (): TestThing => TestThing::query()->create(['name' => 'B', 'code' => '1']));

    // Raw queries bypass the scope entirely — this is DOCUMENTED, intended.
    $context->run($a, function (): void {
        expect(DB::table('test_things')->count())->toBe(2);
    });
});

it('hydrates tenant rows scoped per tenant via the hydrator trap', function () {
    // TenantHydrator reads the central tenants table which has no
    // tenant_id — it must never be affected by the scope.
    $a = tenantIdFor('sekolah-a');

    expect(TenantHydrator::find($a)?->id)->toBe($a);
});
