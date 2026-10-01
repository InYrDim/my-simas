<?php

use Modules\Platform\Database\Factories\ProviderUserFactory;

require_once __DIR__.'/Support/hosts.php';

/**
 * Proves the browser suite itself: pages render without JavaScript
 * errors, a login survives across requests, and the provider console is
 * reachable on its own host.
 */
it('renders the applicant entry pages without JavaScript errors', function () {
    $page = visit('/daftar-sekolah');

    $page->assertSee('Daftarkan sekolah Anda')
        ->assertNoJavaScriptErrors();

    // A second full page load must be its own page, not the first again.
    $page->navigate('/pemohon/masuk')
        ->assertSee('Masuk sebagai pemohon')
        ->assertNoJavaScriptErrors();
});

it('keeps a provider signed in on the console host', function () {
    ProviderUserFactory::new()->create(['email' => 'admin@simas.test', 'password' => 'rahasia-sekali']);

    onConsoleHost();

    $page = visit('/login');

    $page->assertSee('Console Provider')
        ->fill('email', 'admin@simas.test')
        ->fill('password', 'rahasia-sekali')
        ->press('Masuk')
        ->assertPathIs('/dashboard');

    // A second page load is still the signed-in provider.
    $page->navigate('/applicants')
        ->assertPathIs('/applicants')
        ->assertSee('Undang pemohon')
        ->assertNoJavaScriptErrors();
});
