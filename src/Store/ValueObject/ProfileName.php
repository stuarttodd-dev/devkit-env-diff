<?php

declare(strict_types=1);

namespace Devkit\Env\Store\ValueObject;

use InvalidArgumentException;

final class ProfileName
{
    /**
     * Validates a profile label and returns a filesystem-safe .env filename stem.
     */
    public static function validate(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Profile name cannot be empty.');
        }

        if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_. -]{0,62}$/', $trimmed) !== 1) {
            throw new InvalidArgumentException(
                'Profile name must start with a letter or number and use only letters, numbers, '
                . 'spaces, ._- (max 63 chars).'
            );
        }

        return $trimmed;
    }

    /**
     * Maps a display name to a single-segment filename like "my-staging.env".
     */
    public static function toFilename(string $validatedName): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9_.-]+/', '-', $validatedName);
        $slug = strtolower(trim((string) $slug, '-'));
        if ($slug === '') {
            throw new InvalidArgumentException('Could not derive a file name from this profile name.');
        }

        return $slug . '.env';
    }
}
