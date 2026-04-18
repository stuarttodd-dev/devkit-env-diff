<?php

declare(strict_types=1);

namespace Devkit\Env\Cli\Command;

use Devkit\Env\Cli\CliCommandName;
use Devkit\Env\Cli\CliGlobalOption;
use Devkit\Env\Cli\CliProgramName;
use Devkit\Env\Cli\ConsoleHelper;
use Devkit\Env\ProjectLayout;
use Devkit\Env\Store\Config\ProjectConfig;
use Devkit\Env\Store\Runner\PostSwitchCommandRunner;
use Devkit\Env\Store\Service\EnvProfileManager;
use Devkit\Env\Store\ValueObject\ProfileName;
use InvalidArgumentException;

/**
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.StaticAccess)
 * @SuppressWarnings(PHPMD.LongVariable)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */
final readonly class UseCommand
{
    private const int EXIT_OK = 0;

    private const int EXIT_ERROR = 2;

    /**
     * @param list<string> $argv arguments after "use"
     */
    public function run(array $argv): int
    {
        if ($this->hasHelpFlag($argv)) {
            $this->printHelp();

            return self::EXIT_OK;
        }

        $positional = [];
        $target = null;
        $backupDir = null;
        $noBackup = false;
        $skipHooks = false;

        try {
            $this->parseArgs($argv, $positional, $target, $backupDir, $noBackup, $skipHooks);
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

        try {
            $manager->apply($validated, !$noBackup, $backupDir, $target);
        } catch (InvalidArgumentException | \RuntimeException $invalidArgumentException) {
            fwrite(STDERR, $invalidArgumentException->getMessage() . "\n");

            return self::EXIT_ERROR;
        }

        $appliedTo = $target ?? $config->targetEnvPath;
        echo sprintf("Applied profile \"%s\" -> %s\n", $validated, $appliedTo);

        if (!$skipHooks) {
            $commands = $config->commandsAfterSwitchForProfile($validated);
            if ($commands !== []) {
                $hookExit = (new PostSwitchCommandRunner())->run($cwd, $commands);
                if ($hookExit !== 0) {
                    return $hookExit;
                }
            }
        }

        return self::EXIT_OK;
    }

    /**
     * @param list<string>                      $argv
     * @param list<string>                      $positional
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function parseArgs(
        array $argv,
        array &$positional,
        ?string &$target,
        ?string &$backupDir,
        bool &$noBackup,
        bool &$skipHooks,
    ): void {
        $positional = [];
        $index = 0;
        $argCount = count($argv);
        while ($index < $argCount) {
            $arg = $argv[$index];
            if (str_starts_with($arg, '--target=')) {
                $value = substr($arg, strlen('--target='));
                if ($value === '') {
                    throw new InvalidArgumentException('--target= requires a path.');
                }

                $target = $value;
                ++$index;

                continue;
            }

            if ($arg === '--target') {
                ++$index;
                $target = $argv[$index] ?? throw new InvalidArgumentException('--target requires a path.');
                ++$index;

                continue;
            }

            if (str_starts_with($arg, '--backup-dir=')) {
                $value = substr($arg, strlen('--backup-dir='));
                if ($value === '') {
                    throw new InvalidArgumentException('--backup-dir= requires a path.');
                }

                $backupDir = $value;
                ++$index;

                continue;
            }

            if ($arg === '--backup-dir') {
                ++$index;
                $backupDir = $argv[$index] ?? throw new InvalidArgumentException('--backup-dir requires a path.');
                ++$index;

                continue;
            }

            if ($arg === '--no-backup') {
                $noBackup = true;
                ++$index;

                continue;
            }

            if ($arg === '--skip-hooks') {
                $skipHooks = true;
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
            fwrite(STDERR, sprintf(
                "No saved profiles. Run: %s %s --name <name>\n",
                CliProgramName::VENDOR_BIN,
                CliCommandName::SAVE
            ));

            return null;
        }

        echo "Select a profile:\n";
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
        $cmd = CliCommandName::USE;
        $config = ProjectLayout::CONFIG_FILE;
        echo <<<TXT
Usage: {$bin} {$cmd} [PROFILE] [--target PATH] [--backup-dir PATH] [--no-backup] [--skip-hooks]

Copy a saved profile onto your working env file (default path: defaultEnv / targetEnv in {$config}, else ".env").
Backs up the previous file under the backup directory unless --no-backup is set.

  --target PATH       File to overwrite (default: defaultEnv / targetEnv from {$config}).
  --backup-dir PATH   Where to store backups (default: env/backups or {$config}).
  --no-backup         Do not backup the current target file before replacing it.
  --skip-hooks        Do not run afterSwitch / afterSwitchProfiles commands from {$config}.

Interactive mode (TTY): prompts with a numbered list if PROFILE is omitted.

After a successful switch, commands from {$config} (afterSwitch, afterSwitchProfiles) run
from the project directory (e.g. php artisan cache:clear).

TXT;
    }
}
