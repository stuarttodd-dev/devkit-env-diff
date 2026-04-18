<?php

declare(strict_types=1);

use Devkit\Env\Diff\Encoder\EnvLineEncoder;

test('EnvLineEncoder writes simple unquoted values', function (): void {
    expect(EnvLineEncoder::line('FOO', 'bar'))->toBe('FOO=bar');
});

test('EnvLineEncoder quotes values with spaces', function (): void {
    expect(EnvLineEncoder::line('X', 'a b'))->toBe('X="a b"');
});

test('EnvLineEncoder rejects invalid key names', function (): void {
    EnvLineEncoder::line('bad-key', '1');
})->throws(\InvalidArgumentException::class);
