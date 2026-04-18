<?php

declare(strict_types=1);

namespace Devkit\Env\Cli\Commands;

use Devkit\Env\Cli\Constants\CliCommandName;
use Devkit\Env\Cli\Constants\CliGlobalOption;
use Devkit\Env\Cli\Constants\CliProgramName;
use Devkit\Env\Cli\Helpers\ConsoleHelper;
use Devkit\Env\Store\Config\ProjectConfig;
use Devkit\Env\Store\Service\EnvProfileManager;
use Devkit\Env\Store\ValueObject\ProfileName;
use InvalidArgumentException;
use RuntimeException;

/**
 * @SuppressWarnings(PHPMD.StaticAccess)
 * @SuppressWarnings(PHPMD.LongVariable)
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */
final readonly class DeleteCommand
{
    private const int EXIT_OK = 0;

    private const int EXIT_ERROR = 2;

    /**
     * @param list<string> $argv arguments after "delete"
     */
    public function run(array $argv): int
    {
        if ($this->hasHelpFlag($argv)) {
            $this->printHelp();

            return self::EXIT_OK;
        }

        $positional = [];
        $force = false;
        try {
            $this->parseArgs($argv, $positional, $force);
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

        $name = $positional[0] ?? null;
        if ($name === null) {
            if (!ConsoleHelper::isInteractive()) {
                fwrite(STDERR, "Profile name is required when not in an interactive terminal.\n");

                return self::EXIT_ERROR;
            }

            $name = $this->promptForProfile($manager);
            if ($name === null) {
                return self::EXIT_ERROR;
            }
        }

        try {
            $validated = ProfileName::validate($name);
        } catch (InvalidArgumentException $invalidArgumentException) {
            fwrite(STDERR, $invalidArgumentException->getMessage() . "\n");

            return self::EXIT_ERROR;
        }

        if (ConsoleHelper::isInteractive() && !$force) {
            $answer = ConsoleHelper::prompt(sprintf('Delete profile "%s"? [y/N] ', $validated));
            $answerLower = strtolower(trim($answer));
            if ($answerLower !== 'y' && $answerLower !== 'yes') {
                echo "Cancelled.\n";

                return self::EXIT_OK;
            }
        }

        try {
            $manager->delete($validated);
        } catch (InvalidArgumentException | RuntimeException $invalidArgumentException) {
            fwrite(STDERR, $invalidArgumentException->getMessage() . "\n");

            return self::EXIT_ERROR;
        }

        echo sprintf("Deleted profile \"%s\".\n", $validated);

        return self::EXIT_OK;
    }

    /**
     * @param list<string>            $argv
     * @param list<string>            $positional
     */
    private function parseArgs(array $argv, array &$positional, bool &$force): void
    {
        $positional = [];
        $index = 0;
        $argCount = count($argv);
        while ($index < $argCount) {
            $arg = $argv[$index];
            if ($arg === '--force') {
                $force = true;
                ++$index;

                continue;
            }

            if (str_starts_with($arg, '--')) {
                throw new InvalidArgumentException(sprintf('Unknown argument: %s', $arg));
            }

            $positional[] = $arg;
            ++$index;
        }
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

    private function promptForProfile(EnvProfileManager $manager): ?string
    {
        $names = $manager->listNames();
        if ($names === []) {
            fwrite(STDERR, "No saved profiles to delete.\n");

            return null;
        }

        echo "Select a profile to delete:\n";
        foreach ($names as $i => $label) {
            echo sprintf("  %d) %s\n", $i + 1, $label);
        }

        $line = ConsoleHelper::prompt('Enter number or name: ');
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

            return $names[$idx - 1];
        }

        return $line;
    }

    private function printHelp(): void
    {
        $bin = CliProgramName::VENDOR_BIN;
        $del = CliCommandName::DELETE;
        echo <<<TXT
Usage: {$bin} {$del} [PROFILE] [--force]

Remove a saved profile from env/registry.json and delete its file under the store directory.
Does not change your current working .env.

  --force   Skip the confirmation prompt (TTY only).

Interactive mode (TTY): prompts for a profile if PROFILE is omitted; asks for confirmation
unless --force is given.

TXT;
    }
}
