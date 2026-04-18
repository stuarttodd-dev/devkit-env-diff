<?php

declare(strict_types=1);

use Devkit\Env\Diff\Parser\EnvFileParser;

test('parses fixture file', function (): void {
    $path = dirname(__DIR__) . '/fixtures/env/simple.env';
    $parsed = (new EnvFileParser())->parseFile($path);

    expect($parsed['APP_NAME'])->toBe('demo');
    expect($parsed['EMPTY'])->toBe('');
    expect($parsed['QUOTED'])->toBe('hello world');
});

test('parses comments fixture', function (): void {
    $path = dirname(__DIR__) . '/fixtures/env/comments.env';
    $parsed = (new EnvFileParser())->parseFile($path);

    expect($parsed['FOO'])->toBe('bar')
        ->and($parsed['BAZ'])->toBe('qux');
});

test('parses utf-8 values', function (): void {
    $path = dirname(__DIR__) . '/fixtures/env/utf8.env';
    $parsed = (new EnvFileParser())->parseFile($path);

    expect($parsed['APP_LABEL'])->toBe('café')
        ->and($parsed['EMOJI'])->toBe('🚀');
});

test('throws when file missing', function (): void {
    (new EnvFileParser())->parseFile('/no/such/.env');
})->throws(\RuntimeException::class);
