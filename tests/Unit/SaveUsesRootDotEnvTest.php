<?php

declare(strict_types=1);

use Devkit\Env\Cli\MainRouter;

test('save without --from reads ./.env even when defaultEnv points elsewhere', function (): void {
    $dir = sys_get_temp_dir() . '/devkit-save-dotenv-' . bin2hex(random_bytes(4));
    mkdir($dir . '/config', 0777, true);
    file_put_contents($dir . '/.devkit-env.json', json_encode([
        'defaultEnv' => 'config/.env.local',
    ], JSON_THROW_ON_ERROR));
    file_put_contents($dir . '/.env', "FROM_DOTENV=1\n");
    file_put_contents($dir . '/config/.env.local', "FROM_CONFIG=1\n");

    $prev = getcwd();
    chdir($dir);
    $argv = ['devkit-env', 'save', '--name', 'myprofile'];
    ob_start();
    $code = (new MainRouter())->run($argv);
    $stdout = ob_get_clean();
    chdir($prev);

    expect($code)->toBe(0)->and($stdout)->toContain('Saved profile');

    $saved = file_get_contents($dir . '/env/myprofile.env');
    expect($saved)->toContain('FROM_DOTENV=1')
        ->and($saved)->not->toContain('FROM_CONFIG');

    unlink($dir . '/env/myprofile.env');
    unlink($dir . '/env/registry.json');
    rmdir($dir . '/env');
    if (is_file($dir . '/.gitignore')) {
        unlink($dir . '/.gitignore');
    }

    unlink($dir . '/.devkit-env.json');
    unlink($dir . '/.env');
    unlink($dir . '/config/.env.local');
    rmdir($dir . '/config');
    rmdir($dir);
});
