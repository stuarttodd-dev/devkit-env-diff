<?php

declare(strict_types=1);

namespace Devkit\Env\Store\Service;

use Devkit\Env\Branding;

/**
 * Appends ignore patterns to the project .gitignore when missing.
 *
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */
final class GitignoreManager
{
    private const string MARKER_BEGIN = '# ' . Branding::CLI_BINARY;

    private const string MARKER_END = '# /' . Branding::CLI_BINARY;

    /**
     * @param list<string> $patterns
     */
    public function ensurePatterns(string $projectRoot, array $patterns): void
    {
        $patterns = array_values(array_filter($patterns, static fn (string $pat): bool => $pat !== ''));
        if ($patterns === []) {
            return;
        }

        $path = $projectRoot . '/.gitignore';
        $existing = is_readable($path) ? (string) file_get_contents($path) : '';
        $lines = ($existing === '' ? [] : preg_split('/\R/', rtrim($existing, "\r\n"))) ?: [];

        $toAdd = [];
        foreach ($patterns as $pattern) {
            if (!$this->linesContainPattern($lines, $pattern)) {
                $toAdd[] = $pattern;
            }
        }

        if ($toAdd === []) {
            return;
        }

        $block = [];
        if ($existing !== '' && !str_ends_with($existing, "\n")) {
            $block[] = '';
        }

        $block[] = self::MARKER_BEGIN;
        foreach ($toAdd as $line) {
            $block[] = $line;
        }

        $block[] = self::MARKER_END;
        $block[] = '';

        $append = implode("\n", $block);
        if (file_put_contents($path, $existing === '' ? ltrim($append) : ($existing . $append), LOCK_EX) === false) {
            fwrite(STDERR, sprintf("Warning: could not write .gitignore at %s\n", $path));
        }
    }

    /**
     * @param list<string> $lines
     */
    private function linesContainPattern(array $lines, string $pattern): bool
    {
        foreach ($lines as $line) {
            if (trim($line) === trim($pattern)) {
                return true;
            }
        }

        return false;
    }
}
