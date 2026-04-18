<?php

declare(strict_types=1);

namespace Devkit\Env\Cli;

use InvalidArgumentException;

/**
 * Parses CLI arguments for {@see CliProgramName::VENDOR_BIN} {@see CliCommandName::MERGE}.
 *
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.ShortVariable)
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
final class MergeArgvParser
{
    /**
     * @param list<string> $argv
     *
     * @return array{
     *     help: bool,
     *     left: ?string,
     *     right: ?string,
     *     out: ?string,
     *     prefer: ?MergeSide,
     *     noInteraction: bool,
     *     mask: bool,
     *     maskKeyPatterns: list<string>,
     *     dryRun: bool
     * }
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function parse(array $argv): array
    {
        $help = false;
        $left = null;
        $right = null;
        $out = null;
        $prefer = null;
        $noInteraction = false;
        $mask = true;
        /** @var list<string> $maskKeyPatterns */
        $maskKeyPatterns = [];
        $dryRun = false;

        $index = 0;
        $count = count($argv);

        while ($index < $count) {
            $arg = $argv[$index];

            if ($arg === CliGlobalOption::HELP_SHORT || $arg === CliGlobalOption::HELP_LONG) {
                $help = true;
                ++$index;

                continue;
            }

            if ($arg === MergeCliOption::NO_MASK) {
                $mask = false;
                ++$index;

                continue;
            }

            if ($arg === MergeCliOption::DRY_RUN_LONG) {
                $dryRun = true;
                ++$index;

                continue;
            }

            if ($arg === CliGlobalOption::NO_INTERACTION_SHORT || $arg === CliGlobalOption::NO_INTERACTION_LONG) {
                $noInteraction = true;
                ++$index;

                continue;
            }

            if (str_starts_with($arg, MergeCliOption::PREFER_EQ_PREFIX)) {
                $prefer = MergeSide::fromPreferArgument(substr($arg, strlen(MergeCliOption::PREFER_EQ_PREFIX)));
                ++$index;

                continue;
            }

            if ($arg === MergeCliOption::PREFER_LONG) {
                ++$index;
                $preferArg = $argv[$index] ?? throw new InvalidArgumentException(
                    sprintf('--prefer requires %s or %s.', MergeSide::Left->value, MergeSide::Right->value)
                );
                $prefer = MergeSide::fromPreferArgument($preferArg);
                ++$index;

                continue;
            }

            if (str_starts_with($arg, MergeCliOption::LEFT_EQ_PREFIX)) {
                $pathFromEq = substr($arg, strlen(MergeCliOption::LEFT_EQ_PREFIX));
                $left = $this->nonEmptyPath(MergeCliOption::LEFT_LONG, $pathFromEq);
                ++$index;

                continue;
            }

            if ($arg === MergeCliOption::LEFT_LONG) {
                ++$index;
                $nextLeft = $argv[$index] ?? throw new InvalidArgumentException(
                    sprintf('%s requires a path.', MergeCliOption::LEFT_LONG)
                );
                $left = $this->nonEmptyPath(MergeCliOption::LEFT_LONG, $nextLeft);
                ++$index;

                continue;
            }

            if (str_starts_with($arg, MergeCliOption::RIGHT_EQ_PREFIX)) {
                $right = $this->nonEmptyPath(
                    MergeCliOption::RIGHT_LONG,
                    substr($arg, strlen(MergeCliOption::RIGHT_EQ_PREFIX))
                );
                ++$index;

                continue;
            }

            if ($arg === MergeCliOption::RIGHT_LONG) {
                ++$index;
                $nextRight = $argv[$index] ?? throw new InvalidArgumentException(
                    sprintf('%s requires a path.', MergeCliOption::RIGHT_LONG)
                );
                $right = $this->nonEmptyPath(MergeCliOption::RIGHT_LONG, $nextRight);
                ++$index;

                continue;
            }

            if (str_starts_with($arg, MergeCliOption::OUT_EQ_PREFIX)) {
                $out = substr($arg, strlen(MergeCliOption::OUT_EQ_PREFIX));
                ++$index;

                continue;
            }

            if ($arg === MergeCliOption::OUT_LONG) {
                ++$index;
                $out = $argv[$index] ?? throw new InvalidArgumentException(
                    sprintf('%s requires a path.', MergeCliOption::OUT_LONG)
                );
                ++$index;

                continue;
            }

            if (str_starts_with($arg, MergeCliOption::MASK_KEY_EQ_PREFIX)) {
                $pattern = substr($arg, strlen(MergeCliOption::MASK_KEY_EQ_PREFIX));
                if ($pattern === '') {
                    throw new InvalidArgumentException(
                        sprintf('%s requires a non-empty pattern.', MergeCliOption::MASK_KEY_EQ_PREFIX)
                    );
                }

                $maskKeyPatterns[] = $pattern;
                ++$index;

                continue;
            }

            if ($arg === MergeCliOption::MASK_KEY_LONG) {
                ++$index;
                $pattern = $argv[$index] ?? throw new InvalidArgumentException(
                    sprintf('%s requires a pattern.', MergeCliOption::MASK_KEY_LONG)
                );
                if ($pattern === '') {
                    throw new InvalidArgumentException(
                        sprintf('%s requires a non-empty pattern.', MergeCliOption::MASK_KEY_LONG)
                    );
                }

                $maskKeyPatterns[] = $pattern;
                ++$index;

                continue;
            }

            throw new InvalidArgumentException(sprintf('Unknown argument: %s', $arg));
        }

        return [
            'help' => $help,
            'left' => $left,
            'right' => $right,
            'out' => $out,
            'prefer' => $prefer,
            'noInteraction' => $noInteraction,
            'mask' => $mask,
            'maskKeyPatterns' => $maskKeyPatterns,
            'dryRun' => $dryRun,
        ];
    }

    private function nonEmptyPath(string $flag, string $path): string
    {
        if ($path === '') {
            throw new InvalidArgumentException(sprintf('%s requires a non-empty path.', $flag));
        }

        return $path;
    }
}
