<?php

declare(strict_types=1);

namespace Devkit\Env\Diff\Reporting;

use Devkit\Env\Diff\Model\ComparisonResult;
use Devkit\Env\Diff\Service\ValueMasker;

final class TextReportFormatter
{
    /**
     * @param array<string, ComparisonResult> $results target environment name => result
     */
    public function format(string $baselineName, array $results, ValueMasker $masker): string
    {
        $blocks = [];
        $blocks[] = sprintf('Baseline: %s', $baselineName);
        $blocks[] = '';

        foreach ($results as $targetName => $result) {
            $lines = [];
            $lines[] = sprintf('── %s (vs %s) ──', $targetName, $baselineName);

            foreach ($result->missing as $row) {
                $lines[] = sprintf('❌ Missing in %s: %s', $targetName, $row['key']);
            }

            foreach ($result->extra as $row) {
                $lines[] = sprintf('⚠️ Extra in %s: %s', $targetName, $row['key']);
            }

            foreach ($result->mismatches as $row) {
                $baselineVal = $masker->mask($row['key'], $row['baseline']);
                $targetVal = $masker->mask($row['key'], $row['target']);
                $lines[] = sprintf(
                    '⚠️ Different value: %s (%s=%s, %s=%s)',
                    $row['key'],
                    $baselineName,
                    $baselineVal,
                    $targetName,
                    $targetVal
                );
            }

            if (!$result->hasDrift()) {
                $lines[] = '✓ No drift (keys and values match baseline).';
            }

            $blocks[] = implode("\n", $lines);
            $blocks[] = '';
        }

        return rtrim(implode("\n", $blocks)) . "\n";
    }
}
