<?php

declare(strict_types=1);

use Devkit\Env\Cli\MainRouter;

test('save accepts positional profile name with --from', function (): void {
    $dir = sys_get_temp_dir() . '/devkit-save-pos-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    $fixture = dirname(__DIR__) . '/fixtures/env/simple.env';
    $prev = getcwd();
    chdir($dir);

    $argv = ['devkit-env', 'save', 'myprofile', '--from', $fixture];
    ob_start();
    $code = (new MainRouter())->run($argv);
    $out = ob_get_clean();
    chdir($prev);

    expect($code)->toBe(0)
        ->and($out)->toContain('Saved profile "myprofile"')
        ->and(is_file($dir . '/env/myprofile.env'))->toBeTrue();

    unlink($dir . '/env/myprofile.env');
    unlink($dir . '/env/registry.json');
    rmdir($dir . '/env');
    if (is_file($dir . '/.gitignore')) {
        unlink($dir . '/.gitignore');
    }

    rmdir($dir);
});
