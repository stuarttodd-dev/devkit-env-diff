<?php

declare(strict_types=1);

use Devkit\Env\Diff\Model\ComparisonResult;
use Devkit\Env\Diff\Reporting\SideBySideReportFormatter;
use Devkit\Env\Diff\Service\ValueMasker;

test('SideBySideReportFormatter prints two columns', function (): void {
    $result = new ComparisonResult(
        [['key' => 'ONLY_BASE', 'baseline' => 'x']],
        [['key' => 'ONLY_TGT', 'target' => 'y']],
        [['key' => 'K', 'baseline' => 'a', 'target' => 'b']],
    );

    $out = (new SideBySideReportFormatter())->format('base', ['tgt' => $result], new ValueMasker(false));

    expect($out)->toContain('Baseline: base')
        ->and($out)->toContain('KEY')
        ->and($out)->toContain('base (baseline)')
        ->and($out)->toContain('tgt (target)')
        ->and($out)->toContain('ONLY_BASE')
        ->and($out)->toContain('missing in tgt');
});
