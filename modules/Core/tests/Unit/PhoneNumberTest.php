<?php

namespace Modules\Core\Tests\Unit;

use Modules\Core\App\Infrastructure\Whatsapp\PhoneNumber;

it('turns the ways a school writes a number into WhatsApp digits', function (?string $input, ?string $expected) {
    expect(PhoneNumber::normalize($input))->toBe($expected);
})->with([
    'local with dashes' => ['0812-3456-7890', '6281234567890'],
    'local with spaces' => ['0812 3456 7890', '6281234567890'],
    'plus and spaces' => ['+62 812 3456 7890', '6281234567890'],
    'already international' => ['6281234567890', '6281234567890'],
    'without the leading zero' => ['81234567890', '6281234567890'],
    'brackets and dots' => ['(0812) 3456.7890', '6281234567890'],
    'another country, written with plus' => ['+60 12-345 6789', '60123456789'],
    'surrounding spaces' => ['  081234567890 ', '6281234567890'],
]);

it('refuses what cannot be a mobile number', function (?string $input) {
    expect(PhoneNumber::normalize($input))->toBeNull();
})->with([
    'null' => [null],
    'empty' => [''],
    'only a dash' => ['-'],
    'words' => ['tidak ada'],
    'too short' => ['0812345'],
    'too long' => ['08123456789012345'],
    'a landline without country code' => ['2112345678'],
]);

it('hides the middle of a number', function () {
    expect(PhoneNumber::mask('6281234567890'))->toBe('62812••••7890')
        ->and(PhoneNumber::mask('60123456789'))->toBe('60123••6789')
        ->and(PhoneNumber::mask(null))->toBe('—');
});
