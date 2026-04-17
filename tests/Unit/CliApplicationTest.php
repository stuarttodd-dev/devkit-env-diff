<?php

declare(strict_types=1);

use Devkit\Env\Cli\MainRouter;

/**
 * @return array{0: int, 1: string}
 */
function runDevkitBinary(array $args): array
{
    $projectRoot = dirname(__DIR__, 2);
    $bin = $projectRoot . '/bin/devkit-env';
    $parts = [PHP_BINARY, $bin];
    foreach ($args as $a) {
        $parts[] = $a;
    }

    $cmd = '';
    foreach ($parts as $p) {
        $cmd .= ($cmd === '' ? '' : ' ') . escapeshellarg((string) $p);
    }

    $cmd .= ' 2>/dev/null';
    exec($cmd, $lines, $code);

    return [$code, implode("\n", $lines)];
}

test('cli exits 0 when two identical envs', function (): void {
    $path = dirname(__DIR__) . '/fixtures/env/simple.env';
    [$code, $out] = runDevkitBinary([
        '--env', 'local=' . $path,
        '--env', 'prod=' . $path,
    ]);

    expect($code)->toBe(0)
        ->and($out)->toContain('Baseline:');
});

test('cli exits 1 when drift', function (): void {
    $dir = dirname(__DIR__) . '/fixtures/env';
    [$code] = runDevkitBinary([
        '--baseline=local',
        '--env', 'local=' . $dir . '/simple.env',
        '--env', 'other=' . $dir . '/comments.env',
    ]);

    expect($code)->toBe(1);
});

test('cli exits 2 without enough envs', function (): void {
    $path = dirname(__DIR__) . '/fixtures/env/simple.env';
    [$code] = runDevkitBinary([
        '--env', 'only=' . $path,
    ]);

    expect($code)->toBe(2);
});

test('cli json format runs', function (): void {
    $path = dirname(__DIR__) . '/fixtures/env/simple.env';
    [$code, $out] = runDevkitBinary([
        '--format=json',
        '--env', 'a=' . $path,
        '--env', 'b=' . $path,
    ]);

    expect($code)->toBe(0)
        ->and($out)->toContain('"baseline"');
});

test('cli side-by-side format runs', function (): void {
    $path = dirname(__DIR__) . '/fixtures/env/simple.env';
    [$code, $out] = runDevkitBinary([
        '--format=wide',
        '--env', 'a=' . $path,
        '--env', 'b=' . $path,
    ]);

    expect($code)->toBe(0)
        ->and($out)->toContain('KEY')
        ->and($out)->toContain('(baseline)');
});

test('MainRouter in-process matches binary exit code', function (): void {
    $path = dirname(__DIR__) . '/fixtures/env/simple.env';
    $argv = ['devkit-env', '--env', 'local=' . $path, '--env', 'prod=' . $path];
    ob_start();
    $code = (new MainRouter())->run($argv);
    ob_end_clean();

    expect($code)->toBe(0);
});
