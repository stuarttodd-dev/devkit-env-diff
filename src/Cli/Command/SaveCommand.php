<?php

declare(strict_types=1);

namespace Devkit\Env\Cli\Command;

use Devkit\Env\Cli\CliCommandName;
use Devkit\Env\Cli\CliGlobalOption;
use Devkit\Env\Cli\CliProgramName;
use Devkit\Env\Cli\ConsoleHelper;
use Devkit\Env\ProjectLayout;
use Devkit\Env\Store\Config\ProjectConfig;
use Devkit\Env\Store\Service\EnvProfileManager;
use Devkit\Env\Store\Validation\SourcePathDiagnostics;
use Devkit\Env\Store\ValueObject\ProfileName;
use InvalidArgumentException;

/**
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 * @SuppressWarnings(PHPMD.StaticAccess)
 * @SuppressWarnings(PHPMD.ElseExpression)
 * @SuppressWarnings(PHPMD.LongVariable)
 */
final readonly class SaveCommand
{
    private const int EXIT_OK = 0;

    private const int EXIT_ERROR = 2;

    /**
     * @param list<string> $argv arguments after "save"
     */
    public function run(array $argv): int
    {
        if ($this->hasHelpFlag($argv)) {
            $this->printHelp();

            return self::EXIT_OK;
        }

        $name = null;
        $from = null;
        $force = false;

        try {
            $this->parseFlags($argv, $name, $from, $force);
        } catch (InvalidArgumentException $invalidArgumentException) {
            fwrite(STDERR, $invalidArgumentException->getMessage() . "\n");

            return self::EXIT_ERROR;
        }

        $cwd = getcwd();
        if ($cwd === false) {
            fwrite(STDERR, "Cannot determine current working directory.\n");

            return self::EXIT_ERROR;
        }

        $config = ProjectConfig::load($cwd);
        $manager = new EnvProfileManager($config);

        $forceInteractive = false;
        if ($name === null) {
            if (!ConsoleHelper::isInteractive()) {
                fwrite(STDERR, sprintf(
                    "Profile name is required when not in an interactive terminal.\n"
                    . "Example: %s %s staging   or   %s %s --name staging\n",
                    CliProgramName::VENDOR_BIN,
                    CliCommandName::SAVE,
                    CliProgramName::VENDOR_BIN,
                    CliCommandName::SAVE
                ));

                return self::EXIT_ERROR;
            }

            $picked = $this->promptForName($manager);
            if ($picked === null) {
                return self::EXIT_ERROR;
            }

            $name = $picked['name'];
            $forceInteractive = $picked['force'];
        }

        if ($forceInteractive) {
            $force = true;
        }

        // No --from: always read the project root .env (not alternate defaultEnv / targetEnv paths).
        $fromPath = $from ?? ($cwd . '/' . ProjectLayout::DEFAULT_ENV_FILE);
        $sourceProblem = SourcePathDiagnostics::whyNotUsableSourceFile($fromPath);
        if ($sourceProblem !== null) {
            fwrite(STDERR, $sourceProblem . "\n");

            return self::EXIT_ERROR;
        }

        try {
            ProfileName::validate($name);
        } catch (InvalidArgumentException $invalidArgumentException) {
            fwrite(STDERR, $invalidArgumentException->getMessage() . "\n");

            return self::EXIT_ERROR;
        }

        try {
            $manager->save($name, $fromPath, $force);
        } catch (InvalidArgumentException | \RuntimeException $e) {
            fwrite(STDERR, $e->getMessage() . "\n");

            return self::EXIT_ERROR;
        }

        echo sprintf("Saved profile \"%s\" from %s\n", $name, $fromPath);

        return self::EXIT_OK;
    }

    /**
     * @param list<string> $argv
     */
    private function hasHelpFlag(array $argv): bool
    {
        foreach ($argv as $arg) {
            if ($arg === CliGlobalOption::HELP_SHORT || $arg === CliGlobalOption::HELP_LONG) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $argv
     */
    private function parseFlags(array $argv, ?string &$name, ?string &$from, bool &$force): void
    {
        $index = 0;
        $argCount = count($argv);
        while ($index < $argCount) {
            $arg = $argv[$index];
            if (str_starts_with($arg, '--name=')) {
                $value = substr($arg, strlen('--name='));
                if ($value === '') {
                    throw new InvalidArgumentException('--name= requires a value.');
                }

                $name = $value;
                ++$index;

                continue;
            }

            if ($arg === '--name') {
                ++$index;
                $name = $argv[$index] ?? throw new InvalidArgumentException('--name requires a value.');
                ++$index;

                continue;
            }

            if (str_starts_with($arg, '--from=')) {
                $value = substr($arg, strlen('--from='));
                if ($value === '') {
                    throw new InvalidArgumentException('--from= requires a path.');
                }

                $from = $value;
                ++$index;

                continue;
            }

            if ($arg === '--from') {
                ++$index;
                $from = $argv[$index] ?? throw new InvalidArgumentException('--from requires a path.');
                ++$index;

                continue;
            }

            if ($arg === '--force') {
                $force = true;
                ++$index;

                continue;
            }

            if (!str_starts_with($arg, '-')) {
                if ($name !== null) {
                    throw new InvalidArgumentException(
                        'Profile name can only be given once (use one of --name or a single positional name).'
                    );
                }

                $name = $arg;
                ++$index;

                continue;
            }

            throw new InvalidArgumentException(sprintf('Unknown argument: %s', $arg));
        }
    }

    /**
     * @return array{name: string, force: bool}|null
     */
    private function promptForName(EnvProfileManager $manager): ?array
    {
        $names = $manager->listNames();
        if ($names !== []) {
            echo "Existing profiles:\n";
            foreach ($names as $i => $label) {
                echo sprintf("  %d) %s\n", $i + 1, $label);
            }

            echo "Enter a number to overwrite that profile, or type a new profile name:\n";
        } else {
            echo "No saved profiles yet. Enter a profile name:\n";
        }

        $line = ConsoleHelper::prompt('> ');
        if ($line === '') {
            fwrite(STDERR, "Aborted.\n");

            return null;
        }

        if (ctype_digit($line)) {
            $idx = (int) $line;
            if ($idx < 1 || $idx > count($names)) {
                fwrite(STDERR, "Invalid selection.\n");

                return null;
            }

            return ['name' => $names[$idx - 1], 'force' => true];
        }

        return ['name' => $line, 'force' => false];
    }

    private function printHelp(): void
    {
        $bin = CliProgramName::VENDOR_BIN;
        $cmd = CliCommandName::SAVE;
        $envDefault = ProjectLayout::DEFAULT_ENV_FILE;
        echo <<<TXT
Usage: {$bin} {$cmd} [NAME] [--name NAME] [--from PATH] [--force]

Copy {$envDefault} (or --from) into the local profile store (under ./env by default).
Omitting --from always reads ./{$envDefault} — not defaultEnv/targetEnv in .devkit-env.json (those affect "use" only).

  NAME            Profile label (positional alternative to --name; required without a TTY if omitted).
  --name NAME     Profile label (required in non-interactive mode if NAME is not given).
  --from PATH     Source file (default: ./{$envDefault} in the project root when omitted).
  --force         Overwrite an existing profile with the same name.

Interactive mode (TTY): choose an existing profile by number to overwrite, or type a new name.

TXT;
    }
}
