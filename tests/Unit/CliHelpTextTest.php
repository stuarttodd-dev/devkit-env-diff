<?php

declare(strict_types=1);

use Devkit\Env\Cli\MainRouter;

test('global --help documents save source as ./.env or --from, not config target', function (): void {
    ob_start();
    (new MainRouter())->run(['devkit-env', '--help']);
    $out = ob_get_clean();

    expect($out)->toContain('Copy ./.env (or --from PATH)')
        ->and($out)->toContain('defaultEnv/targetEnv from')
        ->not->toContain('current target');
});

test('save --help says omitting --from ignores defaultEnv for source', function (): void {
    ob_start();
    (new MainRouter())->run(['devkit-env', 'save', '--help']);
    $out = ob_get_clean();

    expect($out)->toContain('not defaultEnv/targetEnv')
        ->and($out)->toContain('those affect "use" only');
});
