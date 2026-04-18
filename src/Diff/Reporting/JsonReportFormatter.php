<?php

declare(strict_types=1);

namespace Devkit\Env\Diff\Reporting;

use Devkit\Env\Diff\Model\ComparisonResult;
use Devkit\Env\Diff\Service\ValueMasker;
use JsonException;

final class JsonReportFormatter
{
    /**
     * @param array<string, ComparisonResult> $results
     *
     * @throws JsonException
     */
    public function format(string $baselineName, array $results, ValueMasker $masker): string
    {
        $payload = [
            'baseline' => $baselineName,
            'results' => [],
        ];

        foreach ($results as $targetName => $result) {
            $payload['results'][$targetName] = [
                'missing' => array_map(
                    fn(array $row): array => [
                        'key' => $row['key'],
                        'baseline' => $masker->mask($row['key'], $row['baseline']),
                    ],
                    $result->missing
                ),
                'extra' => array_map(
                    fn(array $row): array => [
                        'key' => $row['key'],
                        'target' => $masker->mask($row['key'], $row['target']),
                    ],
                    $result->extra
                ),
                'different' => array_map(
                    fn(array $row): array => [
                        'key' => $row['key'],
                        'baseline' => $masker->mask($row['key'], $row['baseline']),
                        'target' => $masker->mask($row['key'], $row['target']),
                    ],
                    $result->mismatches
                ),
                'hasDrift' => $result->hasDrift(),
            ];
        }

        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
