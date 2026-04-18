<?php

declare(strict_types=1);

namespace Devkit\Env\Cli\Parsers;

use Devkit\Env\Cli\Constants\CliGlobalOption;
use Devkit\Env\Cli\Constants\CliProgramName;
use Devkit\Env\Cli\Enums\DiffOutputFormat;
use InvalidArgumentException;

/**
 * Parses CLI arguments for {@see CliProgramName::VENDOR_BIN} diff.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
final class DiffArgvParser
{
    /**
     * @param list<string> $argv
     *
     * @return array{
     *     help: bool,
     *     envs: array<string, string>,
     *     profileNames: list<string>,
     *     baseline: ?string,
     *     format: DiffOutputFormat,
     *     mask: bool,
     *     maskKeyPatterns: list<string>
     * }
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function parse(array $argv): array
    {
        $help = false;
        /** @var array<string, string> $envs */
        $envs = [];
        /** @var list<string> $profileNames */
        $profileNames = [];
        $baseline = null;
        $format = DiffOutputFormat::Text;
        $mask = true;
        /** @var list<string> $maskKeyPatterns */
        $maskKeyPatterns = [];

        $index = 0;
        $count = count($argv);

        while ($index < $count) {
            $arg = $argv[$index];

            if ($arg === CliGlobalOption::HELP_SHORT || $arg === CliGlobalOption::HELP_LONG) {
                $help = true;
                ++$index;

                continue;
            }

            if ($arg === '--no-mask') {
                $mask = false;
                ++$index;

                continue;
            }

            if (str_starts_with($arg, '--format=')) {
                $format = DiffOutputFormat::fromUserString(substr($arg, strlen('--format=')));
                ++$index;

                continue;
            }

            if ($arg === '--format') {
                ++$index;
                $next = $argv[$index] ?? null;
                if ($next === null) {
                    throw new InvalidArgumentException('--format requires a value.');
                }

                $format = DiffOutputFormat::fromUserString($next);
                ++$index;

                continue;
            }

            if (str_starts_with($arg, '--baseline=')) {
                $value = substr($arg, strlen('--baseline='));
                if ($value === '') {
                    throw new InvalidArgumentException('--baseline= requires a non-empty name.');
                }

                $baseline = $value;
                ++$index;

                continue;
            }

            if ($arg === '--baseline') {
                ++$index;
                $baselineValue = $argv[$index] ?? throw new InvalidArgumentException('--baseline requires a value.');
                if ($baselineValue === '') {
                    throw new InvalidArgumentException('--baseline requires a non-empty name.');
                }

                $baseline = $baselineValue;
                ++$index;

                continue;
            }

            if (str_starts_with($arg, '--mask-key=')) {
                $pattern = substr($arg, strlen('--mask-key='));
                if ($pattern === '') {
                    throw new InvalidArgumentException('--mask-key= requires a non-empty pattern.');
                }

                $maskKeyPatterns[] = $pattern;
                ++$index;

                continue;
            }

            if ($arg === '--mask-key') {
                ++$index;
                $pattern = $argv[$index] ?? throw new InvalidArgumentException('--mask-key requires a pattern.');
                if ($pattern === '') {
                    throw new InvalidArgumentException('--mask-key requires a non-empty pattern.');
                }

                $maskKeyPatterns[] = $pattern;
                ++$index;

                continue;
            }

            if (str_starts_with($arg, '--env=')) {
                $this->addEnvPair($envs, substr($arg, strlen('--env=')));
                ++$index;

                continue;
            }

            if ($arg === '--env') {
                ++$index;
                $pair = $argv[$index] ?? throw new InvalidArgumentException('--env requires name=path.');
                $this->addEnvPair($envs, $pair);
                ++$index;

                continue;
            }

            if (str_starts_with($arg, '-')) {
                throw new InvalidArgumentException(sprintf('Unknown argument: %s', $arg));
            }

            $profileNames[] = $arg;
            ++$index;
        }

        return [
            'help' => $help,
            'envs' => $envs,
            'profileNames' => $profileNames,
            'baseline' => $baseline,
            'format' => $format,
            'mask' => $mask,
            'maskKeyPatterns' => $maskKeyPatterns,
        ];
    }

    /**
     * @param array<string, string> $envs
     */
    private function addEnvPair(array &$envs, string $pair): void
    {
        $pos = strpos($pair, '=');
        if ($pos === false) {
            throw new InvalidArgumentException(sprintf('Invalid --env value "%s" (expected name=path).', $pair));
        }

        $name = trim(substr($pair, 0, $pos));
        $path = trim(substr($pair, $pos + 1));
        if ($name === '' || $path === '') {
            throw new InvalidArgumentException(sprintf('Invalid --env value "%s" (expected name=path).', $pair));
        }

        if (isset($envs[$name])) {
            throw new InvalidArgumentException(sprintf('Duplicate environment name: %s', $name));
        }

        $envs[$name] = $path;
    }
}
