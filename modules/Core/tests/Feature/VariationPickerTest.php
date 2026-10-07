<?php

namespace Modules\Core\Tests\Feature;

use Modules\Core\App\Infrastructure\Whatsapp\VariationPicker;

require_once __DIR__.'/Support/helpers.php';

/*
 * A wording's groups never repeat the choice of the school's previous
 * message of the same wording.
 */

it('never shows a group the choice it showed in the previous message', function () {
    $tenant = schoolAs('variation-a');
    $template = '{a|b|c} {x|y} {1|2|3|4}';

    $previous = null;

    for ($i = 0; $i < 60; $i++) {
        $parts = explode(' ', inSchool($tenant, fn () => app(VariationPicker::class)->fill($template, [])));

        if ($previous !== null) {
            foreach ($parts as $position => $part) {
                expect($part)->not->toBe($previous[$position]);
            }
        }

        $previous = $parts;
    }
});

it('keeps each school and each wording apart', function () {
    $one = schoolAs('variation-b1');
    $two = schoolAs('variation-b2');

    $first = inSchool($one, fn () => app(VariationPicker::class)->fill('{a|b}', []));
    $other = inSchool($two, fn () => app(VariationPicker::class)->fill('{a|b}', []));
    $again = inSchool($one, fn () => app(VariationPicker::class)->fill('{a|b}', []));

    expect($first)->toBeIn(['a', 'b'])
        ->and($other)->toBeIn(['a', 'b'])
        ->and($again)->not->toBe($first);
});

it('fills the variables after the groups and leaves a template without groups alone', function () {
    $tenant = schoolAs('variation-c');

    expect(inSchool($tenant, fn () => app(VariationPicker::class)->fill('Halo {nama}', ['nama' => 'a|b'])))->toBe('Halo a|b');
});
