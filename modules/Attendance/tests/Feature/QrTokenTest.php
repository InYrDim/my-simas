<?php

namespace Modules\Attendance\Tests\Feature;

use Modules\Attendance\App\Domain\Qr\QrTokens;
use Modules\Platform\Database\Factories\TenantFactory;

require_once __DIR__.'/Support/helpers.php';

/*
 * The one-time code behind a student's QR.
 */

it('gives the student back once', function () {
    $tenant = TenantFactory::new()->create();
    $tokens = app(QrTokens::class);

    $issued = attendanceSchool($tenant, fn () => $tokens->issue(41));

    expect($issued['expiresIn'])->toBe(60)
        ->and(strlen($issued['token']))->toBe(40)
        ->and(attendanceSchool($tenant, fn () => $tokens->consume($issued['token'])))->toBe(41)
        ->and(attendanceSchool($tenant, fn () => $tokens->consume($issued['token'])))->toBeNull();
});

it('expires after a minute', function () {
    $tenant = TenantFactory::new()->create();
    $tokens = app(QrTokens::class);

    $fresh = attendanceSchool($tenant, fn () => $tokens->issue(41));
    $this->travel(59)->seconds();

    expect(attendanceSchool($tenant, fn () => $tokens->consume($fresh['token'])))->toBe(41);

    $stale = attendanceSchool($tenant, fn () => $tokens->issue(41));
    $this->travel(61)->seconds();

    expect(attendanceSchool($tenant, fn () => $tokens->consume($stale['token'])))->toBeNull();
});

it('kills the previous code when the student asks for a new one', function () {
    $tenant = TenantFactory::new()->create();
    $tokens = app(QrTokens::class);

    $first = attendanceSchool($tenant, fn () => $tokens->issue(41));
    $second = attendanceSchool($tenant, fn () => $tokens->issue(41));
    $neighbour = attendanceSchool($tenant, fn () => $tokens->issue(42));

    expect($second['token'])->not->toBe($first['token'])
        ->and(attendanceSchool($tenant, fn () => $tokens->consume($first['token'])))->toBeNull()
        ->and(attendanceSchool($tenant, fn () => $tokens->consume($second['token'])))->toBe(41)
        // One student's new code leaves another student's alone.
        ->and(attendanceSchool($tenant, fn () => $tokens->consume($neighbour['token'])))->toBe(42);
});

it('does not know a code of another school or a made-up one', function () {
    $tenant = TenantFactory::new()->create();
    $other = TenantFactory::new()->create();
    $tokens = app(QrTokens::class);

    $issued = attendanceSchool($other, fn () => $tokens->issue(41));

    expect(attendanceSchool($tenant, fn () => $tokens->consume($issued['token'])))->toBeNull()
        ->and(attendanceSchool($tenant, fn () => $tokens->consume('bukan-kode')))->toBeNull()
        // Trying it at the wrong school did not use it up at the right one.
        ->and(attendanceSchool($other, fn () => $tokens->consume($issued['token'])))->toBe(41);
});
