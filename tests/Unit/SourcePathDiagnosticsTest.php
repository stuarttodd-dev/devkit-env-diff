<?php

declare(strict_types=1);

use Devkit\Env\Store\SourcePathDiagnostics;

test('SourcePathDiagnostics accepts a readable regular file', function (): void {
    $path = dirname(__DIR__) . '/fixtures/env/simple.env';
    expect(SourcePathDiagnostics::isUsableSourceFile($path))->toBeTrue()
        ->and(SourcePathDiagnostics::whyNotUsableSourceFile($path))->toBeNull();
});

test('SourcePathDiagnostics explains missing file', function (): void {
    $path = sys_get_temp_dir() . '/no-such-env-' . bin2hex(random_bytes(4));
    $msg = SourcePathDiagnostics::whyNotUsableSourceFile($path);
    expect($msg)->not->toBeNull()
        ->and($msg)->toContain('does not exist');
});
