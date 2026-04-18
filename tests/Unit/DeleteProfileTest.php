<?php

declare(strict_types=1);

use Devkit\Env\Store\Config\ProjectConfig;
use Devkit\Env\Store\Service\EnvProfileManager;

test('EnvProfileManager deletes profile and file', function (): void {
    $dir = sys_get_temp_dir() . '/devkit-del-' . bin2hex(random_bytes(4));
    mkdir($dir . '/env', 0777, true);
    file_put_contents($dir . '/env/staging.env', "A=1\n");
    file_put_contents($dir . '/env/registry.json', json_encode([
        'version' => 1,
        'profiles' => ['staging' => 'staging.env'],
    ], JSON_THROW_ON_ERROR) . "\n");

    $config = new ProjectConfig($dir, 'env', 'env/backups', '.env', [], []);
    $manager = new EnvProfileManager($config);
    $manager->delete('staging');

    expect(is_file($dir . '/env/staging.env'))->toBeFalse();

    $raw = file_get_contents($dir . '/env/registry.json');
    expect($raw)->not->toBeFalse();
    /** @var array{profiles: array<string, string>} $data */
    $data = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
    expect($data['profiles'])->toBe([]);

    unlink($dir . '/env/registry.json');
    rmdir($dir . '/env');
    if (is_file($dir . '/.gitignore')) {
        unlink($dir . '/.gitignore');
    }

    rmdir($dir);
});
