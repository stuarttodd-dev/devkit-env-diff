<?php

declare(strict_types=1);

namespace Devkit\Env\Diff\Reporting;

use Devkit\Env\Diff\Model\ComparisonResult;
use Devkit\Env\Diff\Service\ValueMasker;

/**
 * Two-column text layout: baseline vs each target (fixed-width cells, truncated).
 *
 * @SuppressWarnings(PHPMD.ShortVariable)
 */
final class SideBySideReportFormatter
{
    private const string COLUMN_KEY_HEADER = 'KEY';

    /** @var string Unicode em dash — absent value in a column */
    private const string PLACEHOLDER_ABSENT = "\u{2014}";

    private const int KEY_WIDTH = 26;

    private const int VAL_WIDTH = 36;

    /**
     * @param array<string, ComparisonResult> $results
     */
    public function format(string $baselineName, array $results, ValueMasker $masker): string
    {
        $blocks = [];
        $blocks[] = sprintf('Baseline: %s', $baselineName);
        $blocks[] = '';

        foreach ($results as $targetName => $result) {
            $lines = [];
            $lines[] = sprintf('── %s (vs %s) ──', $targetName, $baselineName);
            $lines[] = $this->headerRow($baselineName, $targetName);
            $lines[] = str_repeat('─', self::KEY_WIDTH + 2 + self::VAL_WIDTH + 2 + self::VAL_WIDTH);

            foreach ($result->missing as $row) {
                $bv = $masker->mask($row['key'], $row['baseline']);
                $lines[] = $this->row(
                    $row['key'],
                    $bv,
                    self::PLACEHOLDER_ABSENT,
                    sprintf('missing in %s', $targetName)
                );
            }

            foreach ($result->extra as $row) {
                $tv = $masker->mask($row['key'], $row['target']);
                $lines[] = $this->row(
                    $row['key'],
                    self::PLACEHOLDER_ABSENT,
                    $tv,
                    sprintf('extra in %s', $targetName)
                );
            }

            foreach ($result->mismatches as $row) {
                $bv = $masker->mask($row['key'], $row['baseline']);
                $tv = $masker->mask($row['key'], $row['target']);
                $lines[] = $this->row($row['key'], $bv, $tv, '');
            }

            if (!$result->hasDrift()) {
                $lines[] = '✓ No drift (keys and values match baseline).';
            }

            $blocks[] = implode("\n", $lines);
            $blocks[] = '';
        }

        return rtrim(implode("\n", $blocks)) . "\n";
    }

    private function headerRow(string $baselineLabel, string $targetLabel): string
    {
        $k = $this->padKey(self::COLUMN_KEY_HEADER);
        $b = $this->padVal(sprintf('%s (baseline)', $baselineLabel));
        $t = $this->padVal(sprintf('%s (target)', $targetLabel));

        return sprintf('%s  %s  %s', $k, $b, $t);
    }

    private function row(string $key, string $leftVal, string $rightVal, string $note): string
    {
        $k = $this->padKey($key);
        $l = $this->padVal($leftVal);
        $r = $this->padVal($rightVal);
        $row = sprintf('%s  %s  %s', $k, $l, $r);
        if ($note !== '') {
            $row .= "\n    " . $note;
        }

        return $row;
    }

    private function padKey(string $s): string
    {
        return $this->fit($s, self::KEY_WIDTH);
    }

    private function padVal(string $s): string
    {
        return $this->fit($s, self::VAL_WIDTH);
    }

    private function fit(string $s, int $width): string
    {
        $clean = str_replace(["\n", "\r"], ' ', $s);
        if (strlen($clean) <= $width) {
            return str_pad($clean, $width);
        }

        return str_pad(substr($clean, 0, max(0, $width - 3)) . '...', $width);
    }
}
