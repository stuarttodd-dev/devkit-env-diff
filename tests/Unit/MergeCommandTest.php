<?php

declare(strict_types=1);

/**
 * @param list<string> $args
 *
 * @return array{0: int, 1: string}
 */
function runMergeCommandBinary(array $args, ?string $cwd = null): array
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

test('merge unions non-interactively without conflicts', function (): void {
    $dir = sys_get_temp_dir() . '/devkit-merge-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/a.env', "A=1\n");
    file_put_contents($dir . '/b.env', "B=2\n");

    [$code, $stdout] = runMergeCommandBinary(['merge', '--left', $dir . '/a.env', '--right', $dir . '/b.env', '-n']);

    expect($code)->toBe(0)
        ->and($stdout)->toContain('A=1')
        ->and($stdout)->toContain('B=2');

    unlink($dir . '/a.env');
    unlink($dir . '/b.env');
    rmdir($dir);
});

test('merge requires prefer when conflicting and non-interactive', function (): void {
    $dir = sys_get_temp_dir() . '/devkit-merge2-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/a.env', "X=left\n");
    file_put_contents($dir . '/b.env', "X=right\n");

    [$code] = runMergeCommandBinary(['merge', '--left', $dir . '/a.env', '--right', $dir . '/b.env', '-n']);

    expect($code)->toBe(2);

    [$code2, $out2] = runMergeCommandBinary([
        'merge',
        '--left',
        $dir . '/a.env',
        '--right',
        $dir . '/b.env',
        '-n',
        '--prefer',
        'left',
    ]);

    expect($code2)->toBe(0)
        ->and($out2)->toContain('X=left');

    unlink($dir . '/a.env');
    unlink($dir . '/b.env');
    rmdir($dir);
});

test('merge dry-run with --out prints merged content and does not create file', function (): void {
    $dir = sys_get_temp_dir() . '/devkit-merge-dry-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/a.env', "A=1\n");
    file_put_contents($dir . '/b.env', "B=2\n");
    $target = $dir . '/merged.env';

    $argv = [
        'merge',
        '--left',
        $dir . '/a.env',
        '--right',
        $dir . '/b.env',
        '-n',
        '--out',
        $target,
        '--dry-run',
    ];
    [$code, $stdout] = runMergeCommandBinary($argv);

    expect($code)->toBe(0)
        ->and($stdout)->toContain('A=1')
        ->and($stdout)->toContain('B=2')
        ->and(is_file($target))->toBeFalse();

    unlink($dir . '/a.env');
    unlink($dir . '/b.env');
    rmdir($dir);
});

test('merge --select requires interactive tty', function (): void {
    $dir = sys_get_temp_dir() . '/devkit-merge-select-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/a.env', "A=1\n");
    file_put_contents($dir . '/b.env', "A=2\n");

    [$code] = runMergeCommandBinary(['merge', '--left', $dir . '/a.env', '--right', $dir . '/b.env', '--select']);

    expect($code)->toBe(2);

    unlink($dir . '/a.env');
    unlink($dir . '/b.env');
    rmdir($dir);
});
