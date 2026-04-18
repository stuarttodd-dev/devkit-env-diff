<?php

declare(strict_types=1);

use Devkit\Env\Store\Config\ProjectConfig;

test('loads defaultEnv from json', function (): void {
    $dir = sys_get_temp_dir() . '/devkit-cfg-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/.devkit-env.json', '{"defaultEnv": "config/.env.local"}');

    $config = ProjectConfig::load($dir);

    expect($config->targetEnvPath)->toBe('config/.env.local');

    unlink($dir . '/.devkit-env.json');
    rmdir($dir);
});

test('targetEnv wins over defaultEnv when both set', function (): void {
    $dir = sys_get_temp_dir() . '/devkit-cfg2-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/.devkit-env.json', json_encode([
        'defaultEnv' => 'ignored.env',
        'targetEnv' => 'winner.env',
    ], JSON_THROW_ON_ERROR));

    $config = ProjectConfig::load($dir);

    expect($config->targetEnvPath)->toBe('winner.env');

    unlink($dir . '/.devkit-env.json');
    rmdir($dir);
});
