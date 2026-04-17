<?php

declare(strict_types=1);

use Devkit\Env\Cli\MainRouter;

test('merge unions non-interactively without conflicts', function (): void {
    $dir = sys_get_temp_dir() . '/devkit-merge-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/a.env', "A=1\n");
    file_put_contents($dir . '/b.env', "B=2\n");

    $argv = ['devkit-env', 'merge', '--left', $dir . '/a.env', '--right', $dir . '/b.env', '-n'];
    ob_start();
    $code = (new MainRouter())->run($argv);
    $stdout = ob_get_clean();

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

    $argv = ['devkit-env', 'merge', '--left', $dir . '/a.env', '--right', $dir . '/b.env', '-n'];
    ob_start();
    $code = (new MainRouter())->run($argv);
    ob_end_clean();

    expect($code)->toBe(2);

    $argv2 = ['devkit-env', 'merge', '--left', $dir . '/a.env', '--right', $dir . '/b.env', '-n', '--prefer', 'left'];
    ob_start();
    $code2 = (new MainRouter())->run($argv2);
    $out2 = ob_get_clean();

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
        'devkit-env',
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
    ob_start();
    $code = (new MainRouter())->run($argv);
    $stdout = ob_get_clean();

    expect($code)->toBe(0)
        ->and($stdout)->toContain('A=1')
        ->and($stdout)->toContain('B=2')
        ->and(is_file($target))->toBeFalse();

    unlink($dir . '/a.env');
    unlink($dir . '/b.env');
    rmdir($dir);
});
