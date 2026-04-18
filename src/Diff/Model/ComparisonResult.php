<?php

declare(strict_types=1);

namespace Devkit\Env\Diff\Model;

/**
 * Result of comparing a target environment against a baseline.
 *
 * @phpstan-type MissingEntry array{key: string, baseline: string}
 * @phpstan-type ExtraEntry array{key: string, target: string}
 * @phpstan-type MismatchEntry array{key: string, baseline: string, target: string}
 */
final readonly class ComparisonResult
{
    /**
     * @param list<MissingEntry> $missing
     * @param list<ExtraEntry>   $extra
     * @param list<MismatchEntry> $mismatches
     */
    public function __construct(
        public array $missing,
        public array $extra,
        public array $mismatches,
    ) {
    }

    public function hasDrift(): bool
    {
        return $this->missing !== [] || $this->extra !== [] || $this->mismatches !== [];
    }
}
