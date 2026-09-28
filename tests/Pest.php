<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Module test suites
|--------------------------------------------------------------------------
|
| Each module carries its own Feature/Unit tests under modules/<Name>/Tests.
| They are bound here with absolute paths because Pest resolves relative
| targets against the tests/ directory.
|
*/

foreach (glob(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'Tests'.DIRECTORY_SEPARATOR.'Feature') ?: [] as $featureDir) {
    pest()->extend(TestCase::class)
        ->use(RefreshDatabase::class)
        ->in($featureDir);
}

foreach (glob(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'Tests'.DIRECTORY_SEPARATOR.'Unit') ?: [] as $unitDir) {
    pest()->extend(TestCase::class)
        ->in($unitDir);
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, it is likely you will want to check that values meet
| certain conditions. Here you can add custom expectations to use in your tests.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
