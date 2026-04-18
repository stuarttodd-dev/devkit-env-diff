<?php

declare(strict_types=1);

/**
 * @param list<string> $args
 *
 * @return array{0: int, 1: string}
 */
function runDiffPositionalBinary(array $args, ?string $cwd = null): array
{
    $projectRoot = dirname(__DIR__, 2);
    $bin = $projectRoot . '/bin/devkit-env';
    $parts = [PHP_BINARY, $bin];
    foreach ($args as $arg) {
        $parts[] = $arg;
    }

    $cmd = '';
    foreach ($parts as $part) {
        $cmd .= ($cmd === '' ? '' : ' ') . escapeshellarg((string) $part);
    }

    if ($cwd !== null) {
        $cmd = 'cd ' . escapeshellarg($cwd) . ' && ' . $cmd;
    }

    $cmd .= ' 2>/dev/null';
    exec($cmd, $lines, $code);

    return [$code, implode("\n", $lines)];
}

test('diff accepts positional saved profile names', function (): void {
    $dir = sys_get_temp_dir() . '/devkit-diff-pos-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    $fixture = dirname(__DIR__) . '/fixtures/env/simple.env';
    [$saveLocal] = runDiffPositionalBinary(['save', 'local', '--from', $fixture], $dir);
    [$saveStaging] = runDiffPositionalBinary(['save', 'staging', '--from', $fixture], $dir);
    [$diffCode, $out] = runDiffPositionalBinary(['diff', 'local', 'staging'], $dir);

    expect($saveLocal)->toBe(0)
        ->and($saveStaging)->toBe(0)
        ->and($diffCode)->toBe(0)
        ->and($out)->toContain('Baseline: local');

    unlink($dir . '/env/local.env');
    unlink($dir . '/env/staging.env');
    unlink($dir . '/env/registry.json');
    rmdir($dir . '/env');
    if (is_file($dir . '/.gitignore')) {
        unlink($dir . '/.gitignore');
    }

    rmdir($dir);
});

test('diff rejects mixing positional profile names with --env', function (): void {
    [$code] = runDiffPositionalBinary(['diff', 'local', '--env', 'staging=staging.env']);

    expect($code)->toBe(2);
});
