<?php

use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;

beforeEach(function () {
    Route::get('/headers-probe', fn (): string => 'ok')->middleware('web');
    Route::get('/headers-own', fn () => response('ok', 200, ['X-Frame-Options' => 'SAMEORIGIN']))->middleware('web');
});

it('hardens every web response', function () {
    get('http://localhost/headers-probe')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()')
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('keeps a header the route already set', function () {
    get('http://localhost/headers-own')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});

it('sends HSTS only on a secure request in production', function () {
    app()['env'] = 'production';

    get('https://localhost/headers-probe')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    get('http://localhost/headers-probe')->assertHeaderMissing('Strict-Transport-Security');
});
