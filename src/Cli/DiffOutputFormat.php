<?php

declare(strict_types=1);

namespace Devkit\Env\Cli;

use InvalidArgumentException;

/**
 * Valid values for diff --format (plus user aliases handled in {@see self::fromUserString}).
 */
enum DiffOutputFormat: string
{
    case Text = 'text';

    case Json = 'json';

    case SideBySide = 'side-by-side';

    public static function fromUserString(string $value): self
    {
        $lower = strtolower($value);

        return match (true) {
            $lower === self::Text->value => self::Text,
            $lower === self::Json->value => self::Json,
            $lower === self::SideBySide->value,
            $lower === 'sidebyside',
            $lower === 'wide' => self::SideBySide,
            default => throw new InvalidArgumentException(
                sprintf('Invalid --format "%s" (use text, json, or side-by-side).', $value)
            ),
        };
    }
}
