<?php

declare(strict_types=1);

/**
 * @param list<string> $args
 *
 * @return array{0: int, 1: string}
 */
function runMergePositionalBinary(array $args, ?string $cwd = null): array
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

test('merge positional profiles cancels without tty confirmation and keeps target unchanged', function (): void {
    $dir = sys_get_temp_dir() . '/devkit-merge-pos-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/a.env', "A=1\n");
    file_put_contents($dir . '/b.env', "B=2\n");
    [$saveLeft] = runMergePositionalBinary(['save', 'local', '--from', $dir . '/a.env'], $dir);
    [$saveRight] = runMergePositionalBinary(['save', 'staging', '--from', $dir . '/b.env'], $dir);
    [$mergeCode] = runMergePositionalBinary(['merge', 'local', 'staging'], $dir);

    $localProfilePath = $dir . '/env/local.env';
    expect($saveLeft)->toBe(0)
        ->and($saveRight)->toBe(0)
        ->and($mergeCode)->toBe(2)
        ->and(file_get_contents($localProfilePath))->toBe("A=1\n");

    unlink($dir . '/a.env');
    unlink($dir . '/b.env');
    unlink($dir . '/env/local.env');
    unlink($dir . '/env/staging.env');
    unlink($dir . '/env/registry.json');
    rmdir($dir . '/env');
    if (is_file($dir . '/.gitignore')) {
        unlink($dir . '/.gitignore');
    }

    rmdir($dir);
});

test('merge positional profiles supports dry-run without writing', function (): void {
    $dir = sys_get_temp_dir() . '/devkit-merge-pos-dry-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/a.env', "A=1\n");
    file_put_contents($dir . '/b.env', "B=2\n");
    [$saveLeft] = runMergePositionalBinary(['save', 'local', '--from', $dir . '/a.env'], $dir);
    [$saveRight] = runMergePositionalBinary(['save', 'staging', '--from', $dir . '/b.env'], $dir);
    [$mergeCode, $stdout] = runMergePositionalBinary(['merge', 'local', 'staging', '--dry-run'], $dir);

    $localProfilePath = $dir . '/env/local.env';
    expect($saveLeft)->toBe(0)
        ->and($saveRight)->toBe(0)
        ->and($mergeCode)->toBe(0)
        ->and($stdout)->toContain('A=1')
        ->and($stdout)->toContain('B=2')
        ->and(file_get_contents($localProfilePath))->toBe("A=1\n");

    unlink($dir . '/a.env');
    unlink($dir . '/b.env');
    unlink($dir . '/env/local.env');
    unlink($dir . '/env/staging.env');
    unlink($dir . '/env/registry.json');
    rmdir($dir . '/env');
    if (is_file($dir . '/.gitignore')) {
        unlink($dir . '/.gitignore');
    }

    rmdir($dir);
});

test('merge without args in non-interactive mode returns usage error', function (): void {
    [$code] = runMergePositionalBinary(['merge']);

    expect($code)->toBe(2);
});
