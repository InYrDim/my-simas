<?php

use Modules\Platform\Database\Factories\TenantFactory;

/*
| The stock starter test asked "/" for a 200. The front door is now a
| tenancy dispatch, so it is tested as one: central host, tenant host,
| guest, signed in.
*/
test('the root shows a new visitor the public landing page', function () {
    config(['billing.trial_days' => 21]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Platform/Landing')
            ->where('trialDays', 21));
});

test('the root keeps sending a guest with a remembered school to the school login', function () {
    TenantFactory::new()->create(['slug' => 'sdn-example']);

    $this->get(school('sdn-example', '/'))->assertRedirect(route('login'));
});

test('the root on the console host sends a guest to the provider login', function () {
    $this->get('http://console.localhost/')->assertRedirect(route('platform.login'));
});

test('the login path on the console host is the provider login', function () {
    $this->get('http://console.localhost/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Platform/Auth/ProviderLogin'));
});

test('the login path on any other host is the school login', function () {
    $this->get('http://localhost/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Identity/Auth/Login'));
});
