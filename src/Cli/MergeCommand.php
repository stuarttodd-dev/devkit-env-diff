<?php

declare(strict_types=1);

namespace Devkit\Env\Cli;

use Devkit\Env\Diff\EnvFileParser;
use Devkit\Env\Diff\EnvLineEncoder;
use Devkit\Env\Diff\ValueMasker;
use InvalidArgumentException;
use RuntimeException;

/**
 * Interactively or automatically merge two .env files into one key=value output.
 *
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.StaticAccess)
 * @SuppressWarnings(PHPMD.LongVariable)
 * @SuppressWarnings(PHPMD.ShortVariable)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
final readonly class MergeCommand
{
    private const int EXIT_OK = 0;

    private const int EXIT_ABORT = 2;

    public function __construct(
        private MergeArgvParser $argvParser = new MergeArgvParser(),
        private EnvFileParser $parser = new EnvFileParser(),
    ) {
    }

    /**
     * @param list<string> $argv arguments after "merge"
     */
    public function run(array $argv): int
    {
        try {
            $options = $this->argvParser->parse($argv);
        } catch (InvalidArgumentException $invalidArgumentException) {
            fwrite(STDERR, $invalidArgumentException->getMessage() . "\n");
            fwrite(STDERR, sprintf(
                "Try: %s %s --help\n",
                CliProgramName::VENDOR_BIN,
                CliCommandName::MERGE
            ));

            return self::EXIT_ABORT;
        }

        if ($options['help']) {
            $this->printHelp();

            return self::EXIT_OK;
        }

        $leftPath = $options['left'];
        $rightPath = $options['right'];
        if ($leftPath === null || $rightPath === null) {
            fwrite(STDERR, "Both --left and --right are required.\n");

            return self::EXIT_ABORT;
        }

        $cwd = getcwd();
        if ($cwd === false) {
            fwrite(STDERR, "Cannot determine current working directory.\n");

            return self::EXIT_ABORT;
        }

        $leftAbs = $this->resolvePath($cwd, $leftPath);
        $rightAbs = $this->resolvePath($cwd, $rightPath);

        try {
            $left = $this->parser->parseFile($leftAbs);
            $right = $this->parser->parseFile($rightAbs);
        } catch (RuntimeException $runtimeException) {
            fwrite(STDERR, $runtimeException->getMessage() . "\n");

            return self::EXIT_ABORT;
        }

        $masker = new ValueMasker($options['mask'], $options['maskKeyPatterns']);

        $noInteraction = $options['noInteraction'] || !ConsoleHelper::isInteractive();
        $prefer = $options['prefer'];

        $keys = array_unique([...array_keys($left), ...array_keys($right)]);
        sort($keys);

        $merged = [];
        foreach ($keys as $key) {
            $inL = array_key_exists($key, $left);
            $inR = array_key_exists($key, $right);

            if ($inL && $inR) {
                if ($left[$key] === $right[$key]) {
                    $merged[$key] = $left[$key];

                    continue;
                }

                if ($noInteraction) {
                    if ($prefer === null) {
                        fwrite(STDERR, sprintf(
                            "Value conflict for \"%s\" but not in a TTY. Use %s %s or %s.\n",
                            $key,
                            MergeCliOption::PREFER_LONG,
                            MergeSide::Left->value,
                            MergeSide::Right->value
                        ));

                        return self::EXIT_ABORT;
                    }

                    $merged[$key] = $prefer->pickValue($left[$key], $right[$key]);

                    continue;
                }

                $choice = $this->promptMismatch($key, $left[$key], $right[$key], $masker);
                if ($choice === null) {
                    fwrite(STDERR, "Aborted.\n");

                    return self::EXIT_ABORT;
                }

                $merged[$key] = $choice;

                continue;
            }

            if ($inL) {
                if ($noInteraction) {
                    $merged[$key] = $left[$key];

                    continue;
                }

                $keep = $this->promptUnilateral($key, MergeSide::Left, $left[$key], $masker);
                if ($keep === null) {
                    fwrite(STDERR, "Aborted.\n");

                    return self::EXIT_ABORT;
                }

                if ($keep) {
                    $merged[$key] = $left[$key];
                }

                continue;
            }

            if ($noInteraction) {
                $merged[$key] = $right[$key];

                continue;
            }

            $keep = $this->promptUnilateral($key, MergeSide::Right, $right[$key], $masker);
            if ($keep === null) {
                fwrite(STDERR, "Aborted.\n");

                return self::EXIT_ABORT;
            }

            if ($keep) {
                $merged[$key] = $right[$key];
            }
        }

        $body = $this->renderEnvBody($merged);
        $out = $options['out'];
        $dryRun = $options['dryRun'];
        $keyCount = count($merged);

        if ($out !== null) {
            $outAbs = $this->resolvePath($cwd, $out);
            if ($dryRun) {
                echo $body;
                fwrite(STDERR, sprintf(
                    "Dry-run: would write %d keys to %s (file not written).\n",
                    $keyCount,
                    $outAbs
                ));

                return self::EXIT_OK;
            }

            if (file_put_contents($outAbs, $body, LOCK_EX) === false) {
                fwrite(STDERR, sprintf("Could not write: %s\n", $outAbs));

                return self::EXIT_ABORT;
            }

            fwrite(STDERR, sprintf("Wrote %d keys to %s\n", $keyCount, $outAbs));

            return self::EXIT_OK;
        }

        echo $body;
        if ($dryRun) {
            fwrite(STDERR, sprintf(
                "Dry-run: %d keys (printed to stdout only; no file written).\n",
                $keyCount
            ));
        }

        return self::EXIT_OK;
    }

    /**
     * @return ?string chosen raw value, or null to quit
     */
    private function promptMismatch(string $key, string $leftVal, string $rightVal, ValueMasker $masker): ?string
    {
        echo sprintf("Conflict: %s\n", $key);
        echo sprintf("  [l] left  = %s\n", $masker->mask($key, $leftVal));
        echo sprintf("  [r] right = %s\n", $masker->mask($key, $rightVal));
        echo "Choose (l/r/q): ";

        $line = ConsoleHelper::prompt('');
        $c = strtolower(substr(trim($line), 0, 1));
        if ($c === MergeInteractiveChoice::QUIT || $c === MergeInteractiveChoice::EMPTY_ACCEPT_DEFAULT) {
            return null;
        }

        if ($c === MergeInteractiveChoice::LEFT) {
            return $leftVal;
        }

        if ($c === MergeInteractiveChoice::RIGHT) {
            return $rightVal;
        }

        return $this->promptMismatch($key, $leftVal, $rightVal, $masker);
    }

    private function promptUnilateral(string $key, MergeSide $side, string $value, ValueMasker $masker): ?bool
    {
        echo sprintf("Only in %s: %s = %s\n", $side->value, $key, $masker->mask($key, $value));
        echo "Include in merge? [y]es / [n]o / [q]uit: ";

        $line = ConsoleHelper::prompt('');
        $c = strtolower(substr(trim($line), 0, 1));
        if ($c === MergeInteractiveChoice::QUIT) {
            return null;
        }

        if ($c === MergeInteractiveChoice::NO) {
            return false;
        }

        if ($c === MergeInteractiveChoice::YES || $c === MergeInteractiveChoice::EMPTY_ACCEPT_DEFAULT) {
            return true;
        }

        return $this->promptUnilateral($key, $side, $value, $masker);
    }

    /**
     * @param array<string, string> $merged
     */
    private function renderEnvBody(array $merged): string
    {
        $lines = [];
        foreach ($merged as $key => $value) {
            try {
                $lines[] = EnvLineEncoder::line($key, $value);
            } catch (InvalidArgumentException $invalidArgumentException) {
                throw new RuntimeException($invalidArgumentException->getMessage(), 0, $invalidArgumentException);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function resolvePath(string $cwd, string $path): string
    {
        if ($path === '' || $path[0] === DIRECTORY_SEPARATOR || (strlen($path) > 2 && $path[1] === ':')) {
            return $path;
        }

        return $cwd . '/' . $path;
    }

    private function printHelp(): void
    {
        $bin = CliProgramName::VENDOR_BIN;
        $cmd = CliCommandName::MERGE;
        $left = MergeSide::Left->value;
        $right = MergeSide::Right->value;
        $leftOpt = MergeCliOption::LEFT_LONG;
        $rightOpt = MergeCliOption::RIGHT_LONG;
        $outOpt = MergeCliOption::OUT_LONG;
        $preferOpt = MergeCliOption::PREFER_LONG;
        $noIntShort = CliGlobalOption::NO_INTERACTION_SHORT;
        $noIntLong = CliGlobalOption::NO_INTERACTION_LONG;
        $noMask = MergeCliOption::NO_MASK;
        $maskKey = MergeCliOption::MASK_KEY_LONG;
        $dryRun = MergeCliOption::DRY_RUN_LONG;
        echo <<<TXT
Usage: {$bin} {$cmd} {$leftOpt} PATH {$rightOpt} PATH [{$outOpt} PATH]
       [{$preferOpt} {$left}|{$right}] [{$noIntShort}|{$noIntLong}] [{$noMask}] [{$maskKey} PATTERN ...]
       [{$dryRun}]

Merge two .env files into one. Keys that match on both sides are copied once. For keys
present on only one side, you choose whether to include them (interactive mode). For
conflicting values, choose left or right (interactive), or pass {$preferOpt} when not in a TTY.

  {$leftOpt} PATH       First file (shown as "{$left}" in prompts)
  {$rightOpt} PATH      Second file
  {$outOpt} PATH        Write merged env here (default: print to stdout)
  {$dryRun}         Show merged output; with {$outOpt}, print what would be written without creating the file
  {$preferOpt} {$left}|{$right}   Resolve value conflicts when stdin is not a TTY (required then)
  {$noIntShort}, {$noIntLong}  Never prompt; union of keys, conflicts resolved by {$preferOpt}
  {$noMask}         Do not mask values in prompts
  {$maskKey} PAT    Extra key glob patterns to mask (repeatable)

TXT;
    }
}
