<?php

declare(strict_types=1);

namespace Devkit\Env\Diff\Service;

use Devkit\Env\Diff\Model\ComparisonResult;

final class EnvironmentComparer
{
    /**
     * @param array<string, string> $baseline
     * @param array<string, string> $target
     */
    public function compare(array $baseline, array $target): ComparisonResult
    {
        $baselineKeys = array_keys($baseline);
        $targetKeys = array_keys($target);

        $missingKeyNames = array_values(array_diff($baselineKeys, $targetKeys));
        sort($missingKeyNames);

        $extraKeyNames = array_values(array_diff($targetKeys, $baselineKeys));
        sort($extraKeyNames);

        $missing = [];
        foreach ($missingKeyNames as $key) {
            $missing[] = ['key' => $key, 'baseline' => $baseline[$key]];
        }

        $extra = [];
        foreach ($extraKeyNames as $key) {
            $extra[] = ['key' => $key, 'target' => $target[$key]];
        }

        $common = array_intersect($baselineKeys, $targetKeys);
        $commonList = array_values($common);
        sort($commonList);

        $mismatches = [];
        foreach ($commonList as $key) {
            if ($baseline[$key] !== $target[$key]) {
                $mismatches[] = [
                    'key' => $key,
                    'baseline' => $baseline[$key],
                    'target' => $target[$key],
                ];
            }
        }

        return new ComparisonResult($missing, $extra, $mismatches);
    }
}
