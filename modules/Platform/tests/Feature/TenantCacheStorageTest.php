<?php

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Modules\Platform\App\Contracts\TenantCache;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantStorage;

it('partitions cache keys per tenant', function () {
    $a = tenantForQueues('sekolah-a');
    $b = tenantForQueues('sekolah-b');
    $context = app(TenantContext::class);
    $cache = app(TenantCache::class);

    $context->run($a, fn () => $cache->put('shared-key', 'from-A', 60));
    $context->run($b, fn () => $cache->put('shared-key', 'from-B', 60));

    $context->run($a, fn () => expect($cache->get('shared-key'))->toBe('from-A'));
    $context->run($b, fn () => expect($cache->get('shared-key'))->toBe('from-B'));

    // Key helper reflects partitioning
    $context->run($a, fn () => expect($cache->key('shared-key'))->toStartWith('tenant:'.$a.':'));
    $context->runWithoutTenant(fn () => expect($cache->key('shared-key'))->toStartWith('central:'));
});

it('remembers and forgets through the tenant cache', function () {
    $a = tenantForQueues('sekolah-a');
    $context = app(TenantContext::class);
    $cache = app(TenantCache::class);

    $context->run($a, function () use ($cache): void {
        $value = $cache->rememberForever('expensive', fn (): string => 'computed');

        expect($value)->toBe('computed')
            ->and($cache->get('expensive'))->toBe('computed')
            ->and($cache->forget('expensive'))->toBeTrue()
            ->and($cache->get('expensive'))->toBeNull();
    });
});

it('does not share central cache entries with tenants', function () {
    $a = tenantForQueues('sekolah-a');
    $context = app(TenantContext::class);
    $cache = app(TenantCache::class);

    $context->runWithoutTenant(fn () => $cache->put('shared-key', 'from-central', 60));

    $context->run($a, fn () => expect($cache->get('shared-key'))->toBeNull());
    $context->runWithoutTenant(fn () => expect($cache->get('shared-key'))->toBe('from-central'));
});

it('locks per tenant so one school never blocks another', function () {
    $a = tenantForQueues('sekolah-a');
    $b = tenantForQueues('sekolah-b');
    $context = app(TenantContext::class);
    $cache = app(TenantCache::class);

    $held = $context->run($a, fn () => tap($cache->lock('job', 30), fn ($lock) => $lock->get()));

    expect($context->run($a, fn (): bool => (bool) $cache->lock('job', 30)->get()))->toBeFalse()
        ->and($context->run($b, fn (): bool => (bool) $cache->lock('job', 30)->get()))->toBeTrue();

    $held->release();
});

it('exposes the underlying cache repository', function () {
    $cache = app(TenantCache::class);

    expect($cache->repository())->toBeInstanceOf(CacheRepository::class);
});

it('partitions storage paths per tenant', function () {
    $a = tenantForQueues('sekolah-a');
    $b = tenantForQueues('sekolah-b');
    $context = app(TenantContext::class);
    $storage = app(TenantStorage::class);

    expect($context->run($a, fn (): string => $storage->path('attendance', 'reports/x.csv')))
        ->toBe('tenants/'.$a.'/attendance/reports/x.csv')
        ->and($context->run($b, fn (): string => $storage->path('attendance', 'reports/x.csv')))
        ->toBe('tenants/'.$b.'/attendance/reports/x.csv')
        ->and($context->runWithoutTenant(fn (): string => $storage->path('attendance', 'tmp.txt')))
        ->toBe('central/attendance/tmp.txt');
});

it('writes and reads files scoped to the current tenant', function () {
    $a = tenantForQueues('sekolah-a');
    $b = tenantForQueues('sekolah-b');
    $context = app(TenantContext::class);
    $storage = app(TenantStorage::class);

    $context->run($a, fn (): bool => $storage->put('core', 'notes.txt', 'tenant A data'));

    $context->run($a, fn () => expect($storage->exists('core', 'notes.txt'))->toBeTrue()
        ->and($storage->get('core', 'notes.txt'))->toBe('tenant A data'));

    // Tenant B cannot see A's file at the same relative path
    $context->run($b, fn () => expect($storage->exists('core', 'notes.txt'))->toBeFalse());

    $context->run($a, fn (): bool => $storage->delete('core', 'notes.txt'));
    $context->run($a, fn () => expect($storage->exists('core', 'notes.txt'))->toBeFalse());
});

it('rejects path traversal out of the tenant partition', function () {
    $a = tenantForQueues('sekolah-a');
    $context = app(TenantContext::class);
    $storage = app(TenantStorage::class);

    $context->run($a, fn () => expect($storage->path('core', '../../secrets.txt'))
        ->not->toContain('..'));
});
