<?php

namespace Modules\Core\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\App\Domain\Models\CalendarEvent;
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
function eventIn(Tenant $tenant, array $attributes = []): CalendarEvent
{
    return inSchool($tenant, fn (): CalendarEvent => CalendarEvent::factory()->create($attributes));
}

it('lists the events by date with the schools own today', function () {
    $tenant = schoolAs('cal-list');
    eventIn($tenant, ['title' => 'Ujian', 'category' => 'exam', 'start_date' => '2026-12-08', 'end_date' => '2026-12-13']);
    eventIn($tenant, ['title' => 'Awal', 'start_date' => '2026-07-13']);

    $this->travelTo('2026-10-02 23:30:00');

    get(school($tenant->slug, '/akademik/kalender'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Core/Academic/Calendar/Index')
        ->has('events', 2)
        ->where('events.0.title', 'Awal')
        ->where('events.0.endDate', null)
        ->where('events.1.title', 'Ujian')
        ->where('events.1.date', '2026-12-08')
        ->where('events.1.endDate', '2026-12-13')
        ->where('events.1.category', 'exam')
        ->has('today')
    );
});

it('adds an event, a range and a single day', function () {
    $tenant = schoolAs('cal-add');

    post(school($tenant->slug, '/akademik/kalender'), [
        'title' => 'Libur semester', 'category' => 'holiday', 'start_date' => '2026-12-22', 'end_date' => '2027-01-03',
    ])->assertRedirect();

    post(school($tenant->slug, '/akademik/kalender'), [
        'title' => 'Rapor', 'category' => 'activity', 'start_date' => '2026-12-20', 'end_date' => '2026-12-20',
    ])->assertRedirect();

    post(school($tenant->slug, '/akademik/kalender'), [
        'title' => 'Upacara', 'category' => 'activity', 'start_date' => '2026-08-17', 'end_date' => '',
    ])->assertRedirect();

    $rows = inSchool($tenant, fn () => CalendarEvent::query()->orderBy('start_date')->get()->keyBy('title'));

    expect($rows['Libur semester']->end_date->toDateString())->toBe('2027-01-03')
        ->and($rows['Libur semester']->category)->toBe('holiday')
        ->and($rows['Rapor']->end_date)->toBeNull()
        ->and($rows['Upacara']->end_date)->toBeNull();
});

it('updates and deletes an event', function () {
    $tenant = schoolAs('cal-edit');
    $event = eventIn($tenant, ['title' => 'Lama', 'category' => 'activity']);

    put(school($tenant->slug, "/akademik/kalender/{$event->id}"), [
        'title' => 'Baru', 'category' => 'exam', 'start_date' => '2026-03-16', 'end_date' => '2026-03-20',
    ])->assertRedirect();

    $fresh = inSchool($tenant, fn () => $event->fresh());
    expect($fresh->title)->toBe('Baru')->and($fresh->category)->toBe('exam')->and($fresh->end_date->toDateString())->toBe('2026-03-20');

    delete(school($tenant->slug, "/akademik/kalender/{$event->id}"))->assertRedirect();

    expect(inSchool($tenant, fn () => CalendarEvent::query()->count()))->toBe(0);
});

it('rejects an invalid event and stores nothing', function (array $payload, string $error) {
    $tenant = schoolAs('cal-invalid');

    post(school($tenant->slug, '/akademik/kalender'), [
        'title' => 'Ujian', 'category' => 'exam', 'start_date' => '2026-03-16', ...$payload,
    ])->assertSessionHasErrors($error);

    expect(inSchool($tenant, fn () => CalendarEvent::query()->count()))->toBe(0);
})->with([
    'range ends before it starts' => [['end_date' => '2026-03-10'], 'end_date'],
    'no title' => [['title' => ''], 'title'],
    'unknown category' => [['category' => 'party'], 'category'],
    'bad start date' => [['start_date' => '16 Maret'], 'start_date'],
    'missing start date' => [['start_date' => ''], 'start_date'],
]);

it('keeps events of another school out and answers 404 for them', function () {
    $tenant = schoolAs('cal-mine');
    $other = TenantFactory::new()->create(['slug' => 'cal-theirs']);
    $foreign = eventIn($other, ['title' => 'Rahasia']);

    get(school($tenant->slug, '/akademik/kalender'))->assertInertia(fn (Assert $page) => $page->has('events', 0));

    put(school($tenant->slug, "/akademik/kalender/{$foreign->id}"), ['title' => 'X', 'category' => 'exam', 'start_date' => '2026-03-16'])->assertNotFound();
    delete(school($tenant->slug, "/akademik/kalender/{$foreign->id}"))->assertNotFound();

    expect(inSchool($other, fn () => $foreign->fresh()->title))->toBe('Rahasia');
});

it('forbids a teacher role from changing the calendar but lets them view', function () {
    $tenant = schoolAs('cal-guru', 'staf-tu');
    $event = eventIn($tenant);

    get(school($tenant->slug, '/akademik/kalender'))->assertOk();
    post(school($tenant->slug, '/akademik/kalender'), ['title' => 'X', 'category' => 'exam', 'start_date' => '2026-03-16'])->assertForbidden();
    put(school($tenant->slug, "/akademik/kalender/{$event->id}"), ['title' => 'X', 'category' => 'exam', 'start_date' => '2026-03-16'])->assertForbidden();
    delete(school($tenant->slug, "/akademik/kalender/{$event->id}"))->assertForbidden();

    expect(inSchool($tenant, fn () => CalendarEvent::query()->count()))->toBe(1);
});

it('redirects a guest who tries to change the calendar', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'cal-guest']);

    post(school($tenant->slug, '/akademik/kalender'), [])->assertRedirect();
});
