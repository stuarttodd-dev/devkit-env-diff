<?php

declare(strict_types=1);

use Devkit\Env\Diff\Service\MultiEnvironmentDiff;

test('compares baseline to targets', function (): void {
    $base = dirname(__DIR__) . '/fixtures/env/simple.env';
    $same = dirname(__DIR__) . '/fixtures/env/simple.env';

    $diff = new MultiEnvironmentDiff();
    $results = $diff->diff('a', [
        'a' => $base,
        'b' => $same,
    ]);

    expect($results)->toHaveKey('b')
        ->and($results['b']->hasDrift())->toBeFalse();
});

test('requires baseline in map', function (): void {
    $diff = new MultiEnvironmentDiff();
    $diff->diff('missing', ['x' => dirname(__DIR__) . '/fixtures/env/simple.env']);
})->throws(\InvalidArgumentException::class);
