<?php

declare(strict_types=1);

namespace Devkit\Env\Cli;

use InvalidArgumentException;

/**
 * Which file wins for merge --prefer and interactive conflict resolution.
 */
enum MergeSide: string
{
    case Left = 'left';

    case Right = 'right';

    public static function fromPreferArgument(string $value): self
    {
        $lower = strtolower($value);

        return match (true) {
            $lower === self::Left->value,
            $lower === 'l' => self::Left,
            $lower === self::Right->value,
            $lower === 'r' => self::Right,
            default => throw new InvalidArgumentException(
                sprintf('Expected %s, %s, l, or r.', self::Left->value, self::Right->value)
            ),
        };
    }

    public function pickValue(string $leftValue, string $rightValue): string
    {
        return $this === self::Left ? $leftValue : $rightValue;
    }
}
