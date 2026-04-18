<?php

declare(strict_types=1);

namespace Devkit\Env\Cli\Commands;

use Devkit\Env\Cli\Constants\CliCommandName;
use Devkit\Env\Cli\Constants\CliProgramName;
use Devkit\Env\Cli\Enums\DiffOutputFormat;
use Devkit\Env\Cli\Parsers\DiffArgvParser;
use Devkit\Env\Diff\Service\MultiEnvironmentDiff;
use Devkit\Env\Diff\Reporting\JsonReportFormatter;
use Devkit\Env\Diff\Reporting\SideBySideReportFormatter;
use Devkit\Env\Diff\Reporting\TextReportFormatter;
use Devkit\Env\Diff\Service\ValueMasker;
use Devkit\Env\Store\Config\ProjectConfig;
use Devkit\Env\Store\Registry\ProfileRegistry;
use InvalidArgumentException;
use JsonException;

/**
 * @SuppressWarnings(PHPMD.LongVariable)
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */
final readonly class DiffCommand
{
    private const int EXIT_OK = 0;

    private const int EXIT_DRIFT = 1;

    private const int EXIT_ERROR = 2;

    public function __construct(
        private DiffArgvParser $argvParser = new DiffArgvParser(),
    ) {
    }

    /**
     * @param list<string> $argv arguments only (no script name)
     */
    public function run(array $argv): int
    {
        try {
            $options = $this->argvParser->parse($argv);
        } catch (InvalidArgumentException $invalidArgumentException) {
            fwrite(STDERR, $invalidArgumentException->getMessage() . "\n");
            fwrite(STDERR, sprintf("Try: %s %s --help\n", CliProgramName::VENDOR_BIN, CliCommandName::DIFF));

            return self::EXIT_ERROR;
        }

        if ($options['help']) {
            $this->printHelp();

            return self::EXIT_OK;
        }

        $envs = $options['envs'];
        $profileNames = $options['profileNames'];
        if ($envs !== [] && $profileNames !== []) {
            fwrite(STDERR, "Use either positional profile names or --env entries, not both.\n");

            return self::EXIT_ERROR;
        }

        if ($envs === [] && $profileNames !== []) {
            try {
                $envs = $this->resolveStoredProfilePaths($profileNames);
            } catch (InvalidArgumentException $invalidArgumentException) {
                fwrite(STDERR, $invalidArgumentException->getMessage() . "\n");

                return self::EXIT_ERROR;
            }
        }

        if (count($envs) < 2) {
            fwrite(STDERR, "At least two environments are required (e.g. diff local staging, or use --env name=path).\n");

            return self::EXIT_ERROR;
        }

        $baseline = $options['baseline'];
        if ($baseline === null) {
            if (count($envs) > 2) {
                fwrite(STDERR, "When comparing more than two environments, --baseline is required.\n");

                return self::EXIT_ERROR;
            }

            $baseline = array_key_first($envs);
        }

        $masker = new ValueMasker($options['mask'], $options['maskKeyPatterns']);

        $diff = new MultiEnvironmentDiff();
        try {
            $results = $diff->diff($baseline, $envs);
        } catch (\Throwable $throwable) {
            fwrite(STDERR, $throwable->getMessage() . "\n");

            return self::EXIT_ERROR;
        }

        $hasDrift = false;
        foreach ($results as $result) {
            if ($result->hasDrift()) {
                $hasDrift = true;
                break;
            }
        }

        try {
            $format = $options['format'];
            if ($format === DiffOutputFormat::Json) {
                $out = (new JsonReportFormatter())->format($baseline, $results, $masker);
                echo $out;

                return $hasDrift ? self::EXIT_DRIFT : self::EXIT_OK;
            }

            if ($format === DiffOutputFormat::SideBySide) {
                $out = (new SideBySideReportFormatter())->format($baseline, $results, $masker);
                echo $out;

                return $hasDrift ? self::EXIT_DRIFT : self::EXIT_OK;
            }

            $out = (new TextReportFormatter())->format($baseline, $results, $masker);
            echo $out;
        } catch (JsonException $jsonException) {
            fwrite(STDERR, $jsonException->getMessage() . "\n");

            return self::EXIT_ERROR;
        }

        return $hasDrift ? self::EXIT_DRIFT : self::EXIT_OK;
    }

    private function printHelp(): void
    {
        $bin = CliProgramName::VENDOR_BIN;
        $cmd = CliCommandName::DIFF;
        $fmtText = DiffOutputFormat::Text->value;
        $fmtJson = DiffOutputFormat::Json->value;
        $fmtSide = DiffOutputFormat::SideBySide->value;
        $help = <<<TXT
Usage: {$bin} {$cmd} --env NAME=PATH [--env NAME=PATH ...] [--baseline NAME]
       {$bin} {$cmd} PROFILE [PROFILE ...] [--baseline NAME]
       [--format {$fmtText}|{$fmtJson}|{$fmtSide}] [--no-mask] [--mask-key PATTERN ...]

Compare .env files between a baseline environment and one or more targets.

  PROFILE           Saved profile name from the local store (repeatable, at least two).
  --env NAME=PATH   Environment label and path to a .env file (repeatable, at least two).
                    Do not mix positional PROFILE names with --env entries.
  --baseline NAME   Which --env label is the source of truth. Required when more than
                    two environments are listed; with exactly two, defaults to the first
                    environment in the order options were given.
  --format {$fmtText}|{$fmtJson}|{$fmtSide}  Output format (default: {$fmtText}). Aliases: wide, sidebyside.
  --no-mask         Show raw values (default is to mask sensitive-looking keys).
  --mask-key PATTERN  Extra fnmatch pattern for keys whose values should be masked (repeatable).

Exit codes: 0 = no drift, 1 = drift or missing/extra keys, 2 = usage or read error.

TXT;
        echo $help;
    }

    /**
     * @param list<string> $profileNames
     *
     * @return array<string, string> profileName => absolutePath
     */
    private function resolveStoredProfilePaths(array $profileNames): array
    {
        $cwd = getcwd();
        if ($cwd === false) {
            throw new InvalidArgumentException('Cannot determine current working directory.');
        }

        $config = ProjectConfig::load($cwd);
        $registry = ProfileRegistry::load($config->registryAbsolutePath());
        $envs = [];
        foreach ($profileNames as $profileName) {
            $filename = $registry->filenameFor($profileName);
            if ($filename === null) {
                throw new InvalidArgumentException(sprintf('Unknown profile: %s', $profileName));
            }

            $envs[$profileName] = $config->storeRootAbsolute() . '/' . $filename;
        }

        return $envs;
    }
}
