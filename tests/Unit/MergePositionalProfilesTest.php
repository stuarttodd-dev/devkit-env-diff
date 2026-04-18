<?php

declare(strict_types=1);

use Devkit\Env\Cli\MainRouter;

test('merge positional profiles cancels without tty confirmation and keeps target unchanged', function (): void {
    $dir = sys_get_temp_dir() . '/devkit-merge-pos-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/a.env', "A=1\n");
    file_put_contents($dir . '/b.env', "B=2\n");
    $prev = getcwd();
    chdir($dir);

    ob_start();
    $saveLeft = (new MainRouter())->run(['devkit-env', 'save', 'local', '--from', $dir . '/a.env']);
    $saveRight = (new MainRouter())->run(['devkit-env', 'save', 'staging', '--from', $dir . '/b.env']);
    $mergeCode = (new MainRouter())->run(['devkit-env', 'merge', 'local', 'staging']);
    ob_end_clean();
    chdir($prev);

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
    $prev = getcwd();
    chdir($dir);

    ob_start();
    $saveLeft = (new MainRouter())->run(['devkit-env', 'save', 'local', '--from', $dir . '/a.env']);
    $saveRight = (new MainRouter())->run(['devkit-env', 'save', 'staging', '--from', $dir . '/b.env']);
    $mergeCode = (new MainRouter())->run(['devkit-env', 'merge', 'local', 'staging', '--dry-run']);
    $stdout = ob_get_clean();
    chdir($prev);

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
    ob_start();
    $code = (new MainRouter())->run(['devkit-env', 'merge']);
    ob_end_clean();

    expect($code)->toBe(2);
});
