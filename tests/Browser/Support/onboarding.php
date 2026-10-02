<?php

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Modules\Platform\Database\Factories\PlanFactory;
use Modules\Platform\Database\Factories\ProviderUserFactory;

require_once __DIR__.'/hosts.php';

/*
 * Helpers for the onboarding browser tests: the plans an applicant can
 * choose from, the provider's console login, and the link inside a queued
 * mail.
 */

/**
 * The two plans the onboarding form offers in these tests.
 */
function seedOnboardingPlans(): void
{
    PlanFactory::new()->create(['key' => 'starter', 'name' => 'Starter', 'sort_order' => 1]);
    PlanFactory::new()->create(['key' => 'standard', 'name' => 'Standard', 'sort_order' => 2]);
}

/**
 * Sign a provider in on the console host and return their page. The
 * console host stays selected; call onCentralHost() before going back to
 * the applicant's pages.
 */
function providerSignsIn(): mixed
{
    ProviderUserFactory::new()->create(['email' => 'provider@simas.test', 'password' => 'rahasia-provider']);

    onConsoleHost();

    $page = visit('/login');

    $page->fill('email', 'provider@simas.test')
        ->fill('password', 'rahasia-provider')
        ->press('Masuk')
        ->assertPathIs('/dashboard');

    return $page;
}

/**
 * Sign an applicant in through the applicant login form and return their
 * page. Where they land depends on their state, so the caller asserts it.
 */
function applicantSignsIn(string $email, string $password): mixed
{
    onCentralHost();

    $page = visit('/pemohon/masuk');

    $page->fill('email', $email)
        ->fill('password', $password)
        ->press('Masuk');

    return $page;
}

/**
 * The path and query of the link carried by a queued mail. Only the path
 * is visited: the host inside the link is the application's public host,
 * not the test server's.
 *
 * @param  class-string<Mailable>  $mailable
 */
function mailedPath(string $mailable, string $property): string
{
    $url = null;

    Mail::assertQueued($mailable, function (Mailable $mail) use (&$url, $property): bool {
        $url = $mail->{$property};

        return true;
    });

    return (string) preg_replace('#^https?://[^/]+#', '', (string) $url);
}

/**
 * Make the next request load its signed-in users again from the session.
 *
 * The browser plugin answers every request from this one PHP process, so
 * an auth guard keeps the user model it loaded earlier — a change made to
 * that user by another request (verifying the email) stays invisible to
 * it. A real server starts fresh on each request; this restores that.
 */
function refreshSignedInUsers(): void
{
    app('auth')->forgetGuards();
}
