<?php

declare(strict_types=1);

namespace Devkit\Env\Diff\Parser;

use Dotenv\Dotenv;
use RuntimeException;

final class EnvFileParser
{
    /**
     * @return array<string, string>
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function parseFile(string $path): array
    {
        if (!is_readable($path)) {
            throw new RuntimeException(sprintf('Cannot read environment file: %s', $path));
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException(sprintf('Cannot read environment file: %s', $path));
        }

        $parsed = Dotenv::parse($content);

        /** @var array<string, string> $out */
        $out = [];
        foreach ($parsed as $key => $value) {
            $out[$key] = $value === null ? '' : (string) $value;
        }

        return $out;
    }
}
