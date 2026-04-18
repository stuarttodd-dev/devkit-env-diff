<?php

declare(strict_types=1);

namespace Devkit\Env\Store;

use Devkit\Env\ProjectLayout;

/**
 * Explains why a path cannot be read as an env source file.
 */
final class SourcePathDiagnostics
{
    public static function isUsableSourceFile(string $path): bool
    {
        return is_file($path) && is_readable($path);
    }

    /**
     * @return non-empty-string|null null when the path is a readable regular file
     */
    public static function whyNotUsableSourceFile(string $path): ?string
    {
        if (self::isUsableSourceFile($path)) {
            return null;
        }

        if (is_link($path) && !file_exists($path)) {
            $target = readlink($path);

            return sprintf(
                '%s is a broken symlink (link target: %s).',
                $path,
                $target !== false ? $target : '?'
            );
        }

        if (!file_exists($path)) {
            return sprintf(
                '%s does not exist. Create it, set defaultEnv in %s, or pass --from PATH.',
                $path,
                ProjectLayout::CONFIG_FILE
            );
        }

        if (is_dir($path)) {
            return sprintf('%s is a directory, not a file.', $path);
        }

        if (!is_readable($path)) {
            return sprintf(
                '%s exists but is not readable by this PHP process (check permissions and open_basedir).',
                $path
            );
        }

        return sprintf('%s is not a regular file.', $path);
    }
}
