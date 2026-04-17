<?php

declare(strict_types=1);

namespace Devkit\Env\Diff;

use InvalidArgumentException;

/**
 * Serialize KEY=value lines compatible with Dotenv parsing (quoted when needed).
 */
final class EnvLineEncoder
{
    /**
     * @throws InvalidArgumentException when the key is not a valid env name
     */
    public static function line(string $key, string $value): string
    {
        if ($key === '' || preg_match('/^[A-Za-z_]\w*$/', $key) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid environment variable name: %s', $key));
        }

        return $key . '=' . self::encodeValue($value);
    }

    private static function encodeValue(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        if (preg_match('/^[A-Za-z0-9_.@:\/+^-]+$/', $value)) {
            return $value;
        }

        return '"' . str_replace(["\\", '"', "\n", "\r", "\t"], ['\\\\', '\\"', '\\n', '\\r', '\\t'], $value) . '"';
    }
}
