<?php

declare(strict_types=1);

namespace Devkit\Env\Cli\Commands;

use Devkit\Env\Cli\Constants\CliCommandName;
use Devkit\Env\Cli\Constants\CliGlobalOption;
use Devkit\Env\Cli\Constants\CliProgramName;
use Devkit\Env\Cli\Constants\MergeCliOption;
use Devkit\Env\Cli\Constants\MergeInteractiveChoice;
use Devkit\Env\Cli\Enums\MergeSide;
use Devkit\Env\Cli\Helpers\ConsoleHelper;
use Devkit\Env\Cli\Parsers\MergeArgvParser;
use Devkit\Env\Diff\Encoder\EnvLineEncoder;
use Devkit\Env\Diff\Parser\EnvFileParser;
use Devkit\Env\Diff\Service\ValueMasker;
use Devkit\Env\Store\Config\ProjectConfig;
use Devkit\Env\Store\Registry\ProfileRegistry;
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

        $profileNames = $options['profileNames'];
        $leftPath = $options['left'];
        $rightPath = $options['right'];
        if ($profileNames === [] && $leftPath === null && $rightPath === null && ConsoleHelper::isInteractive()) {
            $picked = $this->promptForProfilePair();
            if ($picked === null) {
                return self::EXIT_ABORT;
            }

            $profileNames = [$picked['target'], $picked['source']];
        }

        if ($profileNames !== [] && ($leftPath !== null || $rightPath !== null)) {
            fwrite(STDERR, "Use either positional profile names or --left/--right paths, not both.\n");

            return self::EXIT_ABORT;
        }

        if ($profileNames !== [] && $options['out'] !== null) {
            fwrite(STDERR, "Positional profile merge writes into the first profile directly; --out is not supported in this mode.\n");

            return self::EXIT_ABORT;
        }

        $leftProfileName = null;
        $overwriteTargetAbs = null;
        if ($profileNames !== []) {
            if (count($profileNames) !== 2) {
                fwrite(STDERR, "Provide exactly two profile names: TARGET SOURCE.\n");

                return self::EXIT_ABORT;
            }

            $resolved = $this->resolveStoredProfilePair($profileNames);
            if ($resolved === null) {
                return self::EXIT_ABORT;
            }

            [$leftPath, $rightPath, $leftProfileName] = $resolved;
        }

        if ($leftPath === null || $rightPath === null) {
            fwrite(STDERR, "Both --left and --right are required, or pass two saved profile names.\n");

            return self::EXIT_ABORT;
        }

        $cwd = getcwd();
        if ($cwd === false) {
            fwrite(STDERR, "Cannot determine current working directory.\n");

            return self::EXIT_ABORT;
        }

        $leftAbs = $this->resolvePath($cwd, $leftPath);
        $rightAbs = $this->resolvePath($cwd, $rightPath);
        if ($leftProfileName !== null) {
            $overwriteTargetAbs = $leftAbs;
        }

        try {
            $left = $this->parser->parseFile($leftAbs);
            $right = $this->parser->parseFile($rightAbs);
        } catch (RuntimeException $runtimeException) {
            fwrite(STDERR, $runtimeException->getMessage() . "\n");

            return self::EXIT_ABORT;
        }

        $masker = new ValueMasker($options['mask'], $options['maskKeyPatterns']);
        $select = $options['select'];
        if ($select && !ConsoleHelper::isInteractive()) {
            fwrite(STDERR, "--select requires an interactive terminal (TTY).\n");

            return self::EXIT_ABORT;
        }

        $noInteraction = $options['noInteraction'] || !ConsoleHelper::isInteractive();
        $prefer = $options['prefer'];
        if ($select) {
            $merged = $this->mergeWithSelection($left, $right, $masker);
            if ($merged === null) {
                fwrite(STDERR, "Aborted.\n");

                return self::EXIT_ABORT;
            }
        } else {
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
        }

        $body = $this->renderEnvBody($merged);
        $out = $options['out'];
        $dryRun = $options['dryRun'];
        $keyCount = count($merged);

        if ($overwriteTargetAbs !== null) {
            if ($dryRun) {
                echo $body;
                fwrite(STDERR, sprintf(
                    "Dry-run: would overwrite profile \"%s\" at %s (file not written).\n",
                    $leftProfileName,
                    $overwriteTargetAbs
                ));

                return self::EXIT_OK;
            }

            echo $body;
            if (!ConsoleHelper::isInteractive()) {
                fwrite(STDERR, "Cancelled: merge into saved profile requires interactive confirmation (TTY).\n");

                return self::EXIT_ABORT;
            }

            $confirm = strtolower(trim(ConsoleHelper::prompt(sprintf(
                'Overwrite profile "%s" with merged result? [y/N] ',
                $leftProfileName
            ))));
            if ($confirm !== 'y' && $confirm !== 'yes') {
                fwrite(STDERR, "Cancelled.\n");

                return self::EXIT_ABORT;
            }

            if (file_put_contents($overwriteTargetAbs, $body, LOCK_EX) === false) {
                fwrite(STDERR, sprintf("Could not write: %s\n", $overwriteTargetAbs));

                return self::EXIT_ABORT;
            }

            fwrite(STDERR, sprintf(
                "Updated profile \"%s\" with %d keys.\n",
                $leftProfileName,
                $keyCount
            ));

            return self::EXIT_OK;
        }

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
     * Tickbox-like selection mode: left is the target, right is the source of optional changes.
     *
     * @param array<string, string> $left
     * @param array<string, string> $right
     *
     * @return ?array<string, string>
     */
    private function mergeWithSelection(array $left, array $right, ValueMasker $masker): ?array
    {
        $changes = $this->buildSelectableChanges($left, $right);
        if ($changes === []) {
            return $left;
        }

        $selectedKeys = $this->promptSelectableChanges($changes, $masker);
        if ($selectedKeys === null) {
            return null;
        }

        $merged = $left;
        foreach ($changes as $change) {
            if (isset($selectedKeys[$change['key']])) {
                $merged[$change['key']] = $change['right'];
            }
        }

        ksort($merged);

        return $merged;
    }

    /**
     * @param array<string, string> $left
     * @param array<string, string> $right
     *
     * @return list<array{key: string, left: ?string, right: string, type: string}>
     */
    private function buildSelectableChanges(array $left, array $right): array
    {
        $changes = [];
        foreach ($right as $key => $rightValue) {
            if (!array_key_exists($key, $left)) {
                $changes[] = ['key' => $key, 'left' => null, 'right' => $rightValue, 'type' => 'add'];

                continue;
            }

            if ($left[$key] !== $rightValue) {
                $changes[] = ['key' => $key, 'left' => $left[$key], 'right' => $rightValue, 'type' => 'update'];
            }
        }

        usort(
            $changes,
            static fn (array $a, array $b): int => strcmp($a['key'], $b['key'])
        );

        return $changes;
    }

    /**
     * @param list<array{key: string, left: ?string, right: string, type: string}> $changes
     *
     * @return ?array<string, true>
     */
    private function promptSelectableChanges(array $changes, ValueMasker $masker): ?array
    {
        $selectedKeys = [];
        foreach ($changes as $change) {
            $selectedKeys[$change['key']] = true;
        }
        $showValues = false;

        while (true) {
            echo "Select right-side changes to apply to the left target:\n";
            foreach ($changes as $i => $change) {
                $isSelected = isset($selectedKeys[$change['key']]) ? '[x]' : '[ ]';
                if ($showValues) {
                    $leftValue = $change['left'] === null
                        ? '(missing)'
                        : $masker->mask($change['key'], $change['left']);
                    $rightValue = $masker->mask($change['key'], $change['right']);
                    echo sprintf(
                        "  %d) %s %s [%s] (%s -> %s)\n",
                        $i + 1,
                        $isSelected,
                        $change['key'],
                        $change['type'],
                        $leftValue,
                        $rightValue
                    );
                } else {
                    echo sprintf(
                        "  %d) %s %s [%s]\n",
                        $i + 1,
                        $isSelected,
                        $change['key'],
                        $change['type']
                    );
                }
            }

            $previewState = $showValues ? 'on' : 'off';
            echo sprintf("Commands: number=toggle, a=all, n=none, v=values(%s), d=done, q=quit\n", $previewState);
            $input = strtolower(trim(ConsoleHelper::prompt('Select: ')));
            if ($input === 'd' || $input === 'done') {
                return $selectedKeys;
            }

            if ($input === 'q' || $input === 'quit') {
                return null;
            }

            if ($input === 'a' || $input === 'all') {
                $selectedKeys = [];
                foreach ($changes as $change) {
                    $selectedKeys[$change['key']] = true;
                }

                continue;
            }

            if ($input === 'n' || $input === 'none') {
                $selectedKeys = [];

                continue;
            }

            if ($input === 'v' || $input === 'values') {
                $showValues = !$showValues;

                continue;
            }

            if (ctype_digit($input)) {
                $idx = (int) $input;
                if ($idx >= 1 && $idx <= count($changes)) {
                    $key = $changes[$idx - 1]['key'];
                    if (isset($selectedKeys[$key])) {
                        unset($selectedKeys[$key]);
                    } else {
                        $selectedKeys[$key] = true;
                    }
                } else {
                    fwrite(STDERR, "Invalid selection.\n");
                }

                continue;
            }

            fwrite(STDERR, "Unknown command.\n");
        }
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
        $select = MergeCliOption::SELECT_LONG;
        echo <<<TXT
Usage: {$bin} {$cmd} {$leftOpt} PATH {$rightOpt} PATH [{$outOpt} PATH]
       {$bin} {$cmd} TARGET_PROFILE SOURCE_PROFILE
       [{$preferOpt} {$left}|{$right}] [{$noIntShort}|{$noIntLong}] [{$noMask}] [{$maskKey} PATTERN ...] [{$select}]
       [{$dryRun}]

Merge two .env files into one. Keys that match on both sides are copied once. For keys
present on only one side, you choose whether to include them (interactive mode). For
conflicting values, choose left or right (interactive), or pass {$preferOpt} when not in a TTY.
With positional profile names, the merged result targets the first profile and requires
interactive confirmation before writing (or cancels).
In an interactive terminal, you can also run "{$bin} {$cmd}" with no arguments and choose
target/source profiles from a numbered list.

  {$leftOpt} PATH       First file (shown as "{$left}" in prompts)
  {$rightOpt} PATH      Second file
  {$outOpt} PATH        Write merged env here (default: print to stdout)
  {$dryRun}         Show merged output; with {$outOpt}, print what would be written without creating the file
  {$preferOpt} {$left}|{$right}   Resolve value conflicts when stdin is not a TTY (required then)
  {$noIntShort}, {$noIntLong}  Never prompt; union of keys, conflicts resolved by {$preferOpt}
  {$select}         Interactive tickbox selector: choose which right-side changes to apply
                    to the left-side target. Commands: number=toggle, a=all, n=none,
                    v=toggle value previews, d=done, q=quit.
  {$noMask}         Do not mask values in prompts
  {$maskKey} PAT    Extra key glob patterns to mask (repeatable)

TXT;
    }

    /**
     * @return array{target: string, source: string}|null
     */
    private function promptForProfilePair(): ?array
    {
        $cwd = getcwd();
        if ($cwd === false) {
            fwrite(STDERR, "Cannot determine current working directory.\n");

            return null;
        }

        $config = ProjectConfig::load($cwd);
        $names = array_keys(ProfileRegistry::load($config->registryAbsolutePath())->all());
        sort($names);
        if (count($names) < 2) {
            fwrite(STDERR, "Need at least two saved profiles to merge.\n");

            return null;
        }

        echo "Select profiles to merge:\n";
        foreach ($names as $i => $name) {
            echo sprintf("  %d) %s\n", $i + 1, $name);
        }

        $target = $this->resolveProfileSelection(ConsoleHelper::prompt('Merge into (number or name): '), $names);
        if ($target === null) {
            return null;
        }

        $source = $this->resolveProfileSelection(ConsoleHelper::prompt('Merge from (number or name): '), $names);
        if ($source === null) {
            return null;
        }

        if ($target === $source) {
            fwrite(STDERR, "Target and source profiles must be different.\n");

            return null;
        }

        return ['target' => $target, 'source' => $source];
    }

    /**
     * @param list<string> $names
     */
    private function resolveProfileSelection(string $input, array $names): ?string
    {
        $trimmed = trim($input);
        if ($trimmed === '') {
            fwrite(STDERR, "Cancelled.\n");

            return null;
        }

        if (ctype_digit($trimmed)) {
            $idx = (int) $trimmed;
            if ($idx < 1 || $idx > count($names)) {
                fwrite(STDERR, "Invalid selection.\n");

                return null;
            }

            return $names[$idx - 1];
        }

        if (!in_array($trimmed, $names, true)) {
            fwrite(STDERR, sprintf("Unknown profile: %s\n", $trimmed));

            return null;
        }

        return $trimmed;
    }

    /**
     * @param list<string> $profileNames
     *
     * @return array{0: string, 1: string, 2: string}|null
     */
    private function resolveStoredProfilePair(array $profileNames): ?array
    {
        $cwd = getcwd();
        if ($cwd === false) {
            fwrite(STDERR, "Cannot determine current working directory.\n");

            return null;
        }

        $config = ProjectConfig::load($cwd);
        $registry = ProfileRegistry::load($config->registryAbsolutePath());
        $leftProfileName = $profileNames[0];
        $rightProfileName = $profileNames[1];
        $leftFile = $registry->filenameFor($leftProfileName);
        if ($leftFile === null) {
            fwrite(STDERR, sprintf("Unknown profile: %s\n", $leftProfileName));

            return null;
        }

        $rightFile = $registry->filenameFor($rightProfileName);
        if ($rightFile === null) {
            fwrite(STDERR, sprintf("Unknown profile: %s\n", $rightProfileName));

            return null;
        }

        return [
            $config->storeRootAbsolute() . '/' . $leftFile,
            $config->storeRootAbsolute() . '/' . $rightFile,
            $leftProfileName,
        ];
    }
}
