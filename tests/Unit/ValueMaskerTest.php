<?php

declare(strict_types=1);

use Devkit\Env\Diff\Service\ValueMasker;

test('masks sensitive keys by default', function (): void {
    $m = ValueMasker::withDefaults(true);

    expect($m->mask('STRIPE_SECRET', 'secret'))->toBe('***');
    expect($m->mask('DB_PASSWORD', 'x'))->toBe('***');
    expect($m->mask('ACCESS_TOKEN', 't'))->toBe('***');
    expect($m->mask('API_KEY', 'k'))->toBe('***');
    expect($m->mask('PUBLIC_KEY', 'k'))->toBe('***');
});

test('does not mask ordinary keys', function (): void {
    $m = ValueMasker::withDefaults(true);

    expect($m->mask('APP_ENV', 'local'))->toBe('local');
    expect($m->mask('CACHE_DRIVER', 'redis'))->toBe('redis');
});

test('respects no-mask', function (): void {
    $m = ValueMasker::withDefaults(false);

    expect($m->mask('STRIPE_SECRET', 'secret'))->toBe('secret');
});

test('additional mask patterns', function (): void {
    $m = ValueMasker::withDefaults(true, ['CUSTOM_*']);

    expect($m->mask('CUSTOM_TOKEN', 'x'))->toBe('***');
});
