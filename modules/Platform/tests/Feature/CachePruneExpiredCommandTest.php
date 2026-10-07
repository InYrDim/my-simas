<?php

use Illuminate\Support\Facades\DB;

it('deletes only the expired rows of the database cache table', function () {
    config(['cache.default' => 'database']);

    DB::table('cache')->insert([
        ['key' => 'old', 'value' => 's:1:"x";', 'expiration' => now()->subMinute()->getTimestamp()],
        ['key' => 'fresh', 'value' => 's:1:"x";', 'expiration' => now()->addMinute()->getTimestamp()],
    ]);

    $this->artisan('cache:prune-expired')
        ->expectsOutput('Deleted 1 expired cache row(s).')
        ->assertSuccessful();

    expect(DB::table('cache')->pluck('key')->all())->toBe(['fresh']);
});

it('leaves other cache stores alone', function () {
    config(['cache.default' => 'array']);

    DB::table('cache')->insert(['key' => 'old', 'value' => 's:1:"x";', 'expiration' => now()->subMinute()->getTimestamp()]);

    $this->artisan('cache:prune-expired')
        ->expectsOutput('The cache store is not "database". Nothing to prune.')
        ->assertSuccessful();

    expect(DB::table('cache')->count())->toBe(1);
});
