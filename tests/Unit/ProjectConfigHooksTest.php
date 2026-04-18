<?php

declare(strict_types=1);

use Devkit\Env\Store\Config\ProjectConfig;

test('loads afterSwitch and afterSwitchProfiles', function (): void {
    $dir = sys_get_temp_dir() . '/devkit-env-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/.devkit-env.json', <<<'JSON'
{
  "afterSwitch": ["echo global"],
  "afterSwitchProfiles": {
    "staging": ["echo staging-only"]
  }
}
JSON
    );

    $config = ProjectConfig::load($dir);

    expect($config->commandsAfterSwitchForProfile('other'))->toBe(['echo global'])
        ->and($config->commandsAfterSwitchForProfile('staging'))->toBe(['echo global', 'echo staging-only']);

    unlink($dir . '/.devkit-env.json');
    rmdir($dir);
});

test('PostSwitchCommandRunner runs a trivial command', function (): void {
    $dir = sys_get_temp_dir() . '/devkit-env-ps-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);

    $runner = new \Devkit\Env\Store\Runner\PostSwitchCommandRunner();
    $php = escapeshellarg(PHP_BINARY);
    $code = $runner->run($dir, [$php . ' -r ' . escapeshellarg('fwrite(STDOUT, "ok");')]);

    expect($code)->toBe(0);

    rmdir($dir);
});
