<?php

declare(strict_types=1);

namespace Devkit\Env\Diff\Service;

use Devkit\Env\Diff\Model\ComparisonResult;
use Devkit\Env\Diff\Parser\EnvFileParser;
use InvalidArgumentException;

/**
 * Loads named environment files and compares each non-baseline env to the baseline.
 */
final readonly class MultiEnvironmentDiff
{
    public function __construct(
        private EnvFileParser $parser = new EnvFileParser(),
        private EnvironmentComparer $comparer = new EnvironmentComparer(),
    ) {
    }

    /**
     * @param array<string, string> $envNameToPath ordered map of environment name => file path
     *
     * @return array<string, ComparisonResult> target env name => comparison vs baseline
     */
    public function diff(string $baselineName, array $envNameToPath): array
    {
        if (!isset($envNameToPath[$baselineName])) {
            throw new InvalidArgumentException(
                sprintf('Baseline environment "%s" is not among the provided --env entries.', $baselineName)
            );
        }

        $baseline = $this->parser->parseFile($envNameToPath[$baselineName]);

        $results = [];
        foreach ($envNameToPath as $name => $path) {
            if ($name === $baselineName) {
                continue;
            }

            $target = $this->parser->parseFile($path);
            $results[$name] = $this->comparer->compare($baseline, $target);
        }

        return $results;
    }
}
