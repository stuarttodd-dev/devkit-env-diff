<?php

declare(strict_types=1);

use Devkit\Env\Cli\MainRouter;

test('diff accepts positional saved profile names', function (): void {
    $dir = sys_get_temp_dir() . '/devkit-diff-pos-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    $fixture = dirname(__DIR__) . '/fixtures/env/simple.env';
    $prev = getcwd();
    chdir($dir);

    ob_start();
    $saveLocal = (new MainRouter())->run(['devkit-env', 'save', 'local', '--from', $fixture]);
    $saveStaging = (new MainRouter())->run(['devkit-env', 'save', 'staging', '--from', $fixture]);
    $diffCode = (new MainRouter())->run(['devkit-env', 'diff', 'local', 'staging']);
    $out = ob_get_clean();
    chdir($prev);

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
    ob_start();
    $code = (new MainRouter())->run(['devkit-env', 'diff', 'local', '--env', 'staging=staging.env']);
    ob_end_clean();

    expect($code)->toBe(2);
});
