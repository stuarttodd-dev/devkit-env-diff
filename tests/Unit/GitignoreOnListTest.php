<?php

declare(strict_types=1);

use Devkit\Env\Branding;
use Devkit\Env\Store\Config\ProjectConfig;
use Devkit\Env\Store\Service\EnvProfileManager;

test('listNames ensures .gitignore excludes the env store', function (): void {
    $dir = sys_get_temp_dir() . '/devkit-gitignore-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);

    $config = new ProjectConfig($dir, 'env', 'env/backups', '.env', [], []);
    $manager = new EnvProfileManager($config);
    $manager->listNames();

    $gitignore = file_get_contents($dir . '/.gitignore');
    expect($gitignore)->not->toBeFalse();
    expect($gitignore)->toContain('# ' . Branding::CLI_BINARY);
    expect($gitignore)->toContain('/env/');

    unlink($dir . '/.gitignore');
    rmdir($dir);
});
