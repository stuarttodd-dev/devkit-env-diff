<?php

declare(strict_types=1);

namespace Devkit\Env\Diff\Service;

/**
 * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
 * @SuppressWarnings(PHPMD.LongVariable)
 */
final readonly class ValueMasker
{
    private const string MASK = '***';

    /**
     * @param list<string> $additionalKeyPatterns fnmatch patterns (case-insensitive against uppercased keys)
     */
    public function __construct(
        private bool $enabled = true,
        private array $additionalKeyPatterns = [],
    ) {
    }

    /**
     * @param list<string> $additionalKeyPatterns
     */
    public static function withDefaults(bool $enabled = true, array $additionalKeyPatterns = []): self
    {
        return new self($enabled, $additionalKeyPatterns);
    }

    public function mask(string $key, string $value): string
    {
        if (!$this->enabled) {
            return $value;
        }

        return $this->shouldMask($key) ? self::MASK : $value;
    }

    private function shouldMask(string $key): bool
    {
        $upper = strtoupper($key);

        foreach ($this->defaultPatterns() as $pattern) {
            if (fnmatch($pattern, $upper)) {
                return true;
            }
        }

        foreach ($this->additionalKeyPatterns as $pattern) {
            if (fnmatch(strtoupper($pattern), $upper)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function defaultPatterns(): array
    {
        return [
            '*_SECRET',
            '*PASSWORD*',
            '*_TOKEN',
            '*_KEY',
            'API_*',
        ];
    }
}
