<?php

namespace Modules\Identity\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Contracts\ResolvesUsers;
use Modules\Identity\Models\User;

test('user model is owned by the identity module and persisted', function () {
    $user = User::factory()->create([
        'email' => 'identity@example.com',
    ]);

    $row = DB::table('users')->where('email', 'identity@example.com')->first();

    expect($row)->not->toBeNull()
        ->and(Hash::check('password', $row->password))->toBeTrue()
        ->and($user->name)->toBe($row->name);
});

test('the identity module resolves users through its public contract', function () {
    User::factory()->create([
        'email' => 'resolver@example.com',
    ]);

    $record = app(ResolvesUsers::class)->findByEmail('resolver@example.com');

    expect($record)->not->toBeNull()
        ->and($record->email)->toBe('resolver@example.com')
        ->and($record->id)->toBeInt();

    expect(app(ResolvesUsers::class)->findByEmail('missing@example.com'))->toBeNull();
});
