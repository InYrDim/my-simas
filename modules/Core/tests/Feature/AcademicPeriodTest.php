<?php

namespace Modules\Core\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\App\Domain\Models\PeriodSlot;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/**
 * @param  array<string, mixed>  $attributes
 */
function slotIn(Tenant $tenant, array $attributes = []): PeriodSlot
{
    return inSchool($tenant, fn (): PeriodSlot => PeriodSlot::factory()->create($attributes));
}

it('lists the seven weekdays with lesson numbers that skip breaks', function () {
    $tenant = schoolAs('pr-list');
    slotIn($tenant, ['day' => 2, 'start_time' => '07:15:00', 'end_time' => '08:00:00']);
    slotIn($tenant, ['day' => 2, 'start_time' => '08:00:00', 'end_time' => '08:15:00', 'type' => 'Istirahat']);
    slotIn($tenant, ['day' => 2, 'start_time' => '08:15:00', 'end_time' => '09:00:00']);
    slotIn($tenant, ['day' => 7, 'start_time' => '07:15:00', 'end_time' => '08:00:00']);

    get(school($tenant->slug, '/akademik/jam-pelajaran'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Core/Academic/Periods/Index')
        ->has('days', 7)
        ->where('days.0.day', 'Senin')
        ->has('days.0.slots', 0)
        ->where('days.1.day', 'Selasa')
        ->where('days.1.slots.0.start', '07:15')
        ->where('days.1.slots.0.order', 1)
        ->where('days.1.slots.1.order', null)
        ->where('days.1.slots.2.order', 2)
        ->where('days.6.day', 'Minggu')
        ->where('days.6.slots.0.start', '07:15')
        ->where('days.6.slots.0.order', 1)
    );
});

it('adds, updates and deletes a slot', function () {
    $tenant = schoolAs('pr-crud');

    post(school($tenant->slug, '/akademik/jam-pelajaran'), ['day' => 1, 'start_time' => '07:15', 'end_time' => '08:00', 'type' => 'Pelajaran'])
        ->assertRedirect();

    $slot = inSchool($tenant, fn () => PeriodSlot::query()->sole());
    expect($slot->day)->toBe(1)->and($slot->start_time)->toBe('07:15:00')->and($slot->end_time)->toBe('08:00:00');

    put(school($tenant->slug, "/akademik/jam-pelajaran/{$slot->id}"), ['day' => 1, 'start_time' => '07:15', 'end_time' => '08:30', 'type' => 'Upacara'])
        ->assertRedirect();

    expect(inSchool($tenant, fn () => $slot->fresh()->only(['end_time', 'type'])))->toBe(['end_time' => '08:30:00', 'type' => 'Upacara']);

    delete(school($tenant->slug, "/akademik/jam-pelajaran/{$slot->id}"))->assertRedirect();

    expect(inSchool($tenant, fn () => PeriodSlot::query()->count()))->toBe(0);
});

it('rejects an invalid slot and stores nothing', function (array $payload, string $error) {
    $tenant = schoolAs('pr-invalid');

    post(school($tenant->slug, '/akademik/jam-pelajaran'), [
        'day' => 1, 'start_time' => '07:15', 'end_time' => '08:00', 'type' => 'Pelajaran', ...$payload,
    ])->assertSessionHasErrors($error);

    expect(inSchool($tenant, fn () => PeriodSlot::query()->count()))->toBe(0);
})->with([
    'ends before it starts' => [['end_time' => '07:00'], 'end_time'],
    'ends when it starts' => [['end_time' => '07:15'], 'end_time'],
    'unknown type' => [['type' => 'Tidur'], 'type'],
    'no day 8' => [['day' => 8], 'day'],
    'badly formatted time' => [['start_time' => '7 pagi'], 'start_time'],
]);

it('refuses overlapping slots on one day but allows adjacent slots and the same time on another day', function () {
    $tenant = schoolAs('pr-overlap');
    slotIn($tenant, ['day' => 1, 'start_time' => '07:15:00', 'end_time' => '08:00:00']);

    post(school($tenant->slug, '/akademik/jam-pelajaran'), ['day' => 1, 'start_time' => '07:45', 'end_time' => '08:30', 'type' => 'Pelajaran'])
        ->assertSessionHasErrors('start_time');
    post(school($tenant->slug, '/akademik/jam-pelajaran'), ['day' => 1, 'start_time' => '07:00', 'end_time' => '07:30', 'type' => 'Pelajaran'])
        ->assertSessionHasErrors('start_time');
    post(school($tenant->slug, '/akademik/jam-pelajaran'), ['day' => 1, 'start_time' => '07:20', 'end_time' => '07:50', 'type' => 'Pelajaran'])
        ->assertSessionHasErrors('start_time');

    post(school($tenant->slug, '/akademik/jam-pelajaran'), ['day' => 1, 'start_time' => '08:00', 'end_time' => '08:45', 'type' => 'Pelajaran'])
        ->assertSessionHasNoErrors();
    post(school($tenant->slug, '/akademik/jam-pelajaran'), ['day' => 2, 'start_time' => '07:15', 'end_time' => '08:00', 'type' => 'Pelajaran'])
        ->assertSessionHasNoErrors();

    expect(inSchool($tenant, fn () => PeriodSlot::query()->count()))->toBe(3);
});

it('lets a slot keep its own times when it is edited', function () {
    $tenant = schoolAs('pr-self');
    $slot = slotIn($tenant);

    put(school($tenant->slug, "/akademik/jam-pelajaran/{$slot->id}"), ['day' => 1, 'start_time' => '07:15', 'end_time' => '08:00', 'type' => 'Istirahat'])
        ->assertSessionHasNoErrors();

    expect(inSchool($tenant, fn () => $slot->fresh()->type))->toBe('Istirahat');
});

it('copies a day over the chosen days, replacing what they had', function () {
    $tenant = schoolAs('pr-copy');
    slotIn($tenant, ['day' => 1, 'start_time' => '07:15:00', 'end_time' => '08:00:00']);
    slotIn($tenant, ['day' => 1, 'start_time' => '08:00:00', 'end_time' => '08:15:00', 'type' => 'Istirahat']);
    slotIn($tenant, ['day' => 2, 'start_time' => '10:00:00', 'end_time' => '11:00:00']);

    post(school($tenant->slug, '/akademik/jam-pelajaran/salin'), ['from_day' => 1, 'days' => [2, 3]])->assertRedirect();

    inSchool($tenant, function (): void {
        expect(PeriodSlot::query()->where('day', 2)->orderBy('start_time')->pluck('start_time')->all())->toBe(['07:15:00', '08:00:00'])
            ->and(PeriodSlot::query()->where('day', 3)->count())->toBe(2)
            ->and(PeriodSlot::query()->where('day', 1)->count())->toBe(2);
    });
});

it('refuses to copy an empty day, onto itself, or onto a day that does not exist', function (array $payload, string $error) {
    $tenant = schoolAs('pr-copy-bad');
    slotIn($tenant, ['day' => 1]);
    slotIn($tenant, ['day' => 2, 'start_time' => '10:00:00', 'end_time' => '11:00:00']);

    post(school($tenant->slug, '/akademik/jam-pelajaran/salin'), $payload)->assertSessionHasErrors($error);

    expect(inSchool($tenant, fn () => PeriodSlot::query()->count()))->toBe(2);
})->with([
    'empty source day' => [['from_day' => 4, 'days' => [2]], 'from_day'],
    'onto itself' => [['from_day' => 1, 'days' => [1, 2]], 'days'],
    'day 9' => [['from_day' => 1, 'days' => [9]], 'days.0'],
    'no target' => [['from_day' => 1, 'days' => []], 'days'],
]);

it('keeps slots of another school out and answers 404 for them', function () {
    $tenant = schoolAs('pr-mine');
    $other = TenantFactory::new()->create(['slug' => 'pr-theirs']);
    $foreign = slotIn($other);

    get(school($tenant->slug, '/akademik/jam-pelajaran'))->assertInertia(fn (Assert $page) => $page->has('days.0.slots', 0));
    put(school($tenant->slug, "/akademik/jam-pelajaran/{$foreign->id}"), ['day' => 1, 'start_time' => '09:00', 'end_time' => '10:00', 'type' => 'Pelajaran'])->assertNotFound();
    delete(school($tenant->slug, "/akademik/jam-pelajaran/{$foreign->id}"))->assertNotFound();

    post(school($tenant->slug, '/akademik/jam-pelajaran'), ['day' => 1, 'start_time' => '07:15', 'end_time' => '08:00', 'type' => 'Pelajaran'])
        ->assertSessionHasNoErrors();

    expect(inSchool($other, fn () => PeriodSlot::query()->count()))->toBe(1);
});

it('forbids a teacher role from changing the schedule but lets them view', function () {
    $tenant = schoolAs('pr-guru', 'guru');
    $slot = slotIn($tenant);

    get(school($tenant->slug, '/akademik/jam-pelajaran'))->assertOk();
    post(school($tenant->slug, '/akademik/jam-pelajaran'), ['day' => 2, 'start_time' => '07:15', 'end_time' => '08:00', 'type' => 'Pelajaran'])->assertForbidden();
    put(school($tenant->slug, "/akademik/jam-pelajaran/{$slot->id}"), ['day' => 1, 'start_time' => '09:00', 'end_time' => '10:00', 'type' => 'Pelajaran'])->assertForbidden();
    delete(school($tenant->slug, "/akademik/jam-pelajaran/{$slot->id}"))->assertForbidden();
    post(school($tenant->slug, '/akademik/jam-pelajaran/salin'), ['from_day' => 1, 'days' => [2]])->assertForbidden();

    expect(inSchool($tenant, fn () => PeriodSlot::query()->count()))->toBe(1);
});

it('redirects a guest who tries to change the schedule', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'pr-guest']);

    post(school($tenant->slug, '/akademik/jam-pelajaran'), [])->assertRedirect();
});
