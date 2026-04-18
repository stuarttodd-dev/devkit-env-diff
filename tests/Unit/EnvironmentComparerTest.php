<?php

declare(strict_types=1);

use Devkit\Env\Diff\Service\EnvironmentComparer;

test('detects missing extra and mismatched values', function (): void {
    $baseline = ['A' => '1', 'B' => '2', 'C' => '3'];
    $target = ['B' => '2', 'C' => '99', 'D' => '4'];

    $r = (new EnvironmentComparer())->compare($baseline, $target);

    expect($r->missing)->toHaveCount(1)
        ->and($r->missing[0]['key'])->toBe('A')
        ->and($r->missing[0]['baseline'])->toBe('1');

    expect($r->extra)->toHaveCount(1)
        ->and($r->extra[0]['key'])->toBe('D')
        ->and($r->extra[0]['target'])->toBe('4');

    expect($r->mismatches)->toHaveCount(1)
        ->and($r->mismatches[0]['key'])->toBe('C')
        ->and($r->mismatches[0]['baseline'])->toBe('3')
        ->and($r->mismatches[0]['target'])->toBe('99');

    expect($r->hasDrift())->toBeTrue();
});

test('no drift when identical', function (): void {
    $a = ['X' => 'y'];
    $r = (new EnvironmentComparer())->compare($a, $a);
    expect($r->hasDrift())->toBeFalse();
});
