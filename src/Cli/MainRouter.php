<?php

declare(strict_types=1);

namespace Devkit\Env\Cli;

use Devkit\Env\Cli\Commands\DeleteCommand;
use Devkit\Env\Cli\Commands\DiffCommand;
use Devkit\Env\Cli\Commands\ListCommand;
use Devkit\Env\Cli\Commands\MergeCommand;
use Devkit\Env\Cli\Commands\SaveCommand;
use Devkit\Env\Cli\Commands\UseCommand;
use Devkit\Env\Cli\Constants\CliCommandName;
use Devkit\Env\Cli\Constants\CliGlobalOption;
use Devkit\Env\Cli\Constants\CliProgramName;
use Devkit\Env\Cli\Constants\ProjectLayout;

/**
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */
final readonly class MainRouter
{
    private const int EXIT_OK = 0;

    private const int EXIT_ERROR = 2;

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        array_shift($argv);

        if ($argv === []) {
            $this->printGlobalHelp();

            return self::EXIT_OK;
        }

        $first = $argv[0];
        if ($first === CliGlobalOption::HELP_SHORT || $first === CliGlobalOption::HELP_LONG) {
            $this->printGlobalHelp();

            return self::EXIT_OK;
        }

        if ($first === CliCommandName::HELP) {
            $this->printGlobalHelp();

            return self::EXIT_OK;
        }

        if ($first === CliCommandName::DIFF || str_starts_with($first, '-')) {
            if ($first === CliCommandName::DIFF) {
                array_shift($argv);
            }

            return (new DiffCommand())->run($argv);
        }

        if ($first === CliCommandName::SAVE) {
            return (new SaveCommand())->run(array_slice($argv, 1));
        }

        if ($first === CliCommandName::USE) {
            return (new UseCommand())->run(array_slice($argv, 1));
        }

        if ($first === CliCommandName::LIST) {
            return (new ListCommand())->run(array_slice($argv, 1));
        }

        if ($first === CliCommandName::DELETE) {
            return (new DeleteCommand())->run(array_slice($argv, 1));
        }

        if ($first === CliCommandName::MERGE) {
            return (new MergeCommand())->run(array_slice($argv, 1));
        }

        $bin = CliProgramName::VENDOR_BIN;
        fwrite(STDERR, sprintf("Unknown command: %s\nRun %s --help\n", $first, $bin));

        return self::EXIT_ERROR;
    }

    private function printGlobalHelp(): void
    {
        $bin = CliProgramName::VENDOR_BIN;
        $diff = CliCommandName::DIFF;
        $merge = CliCommandName::MERGE;
        $save = CliCommandName::SAVE;
        $use = CliCommandName::USE;
        $list = CliCommandName::LIST;
        $delete = CliCommandName::DELETE;
        $config = ProjectLayout::CONFIG_FILE;
        echo <<<TXT
{$bin} — switch between saved .env profiles and compare environments.

Commands:
  {$diff}    Compare .env files (drift report). Run: {$bin} {$diff} --help
  {$merge}   Merge two .env files (interactive or --prefer). Run: {$bin} {$merge} --help
  {$save}    Copy ./.env (or --from PATH) into a named profile under ./env/
  {$use}     Apply a named profile onto defaultEnv/targetEnv from {$config} (with backup by default)
  {$list}    List saved profile names
  {$delete}  Remove a saved profile

Configuration (optional): {$config} in the project root
  storeDir, backupDir, defaultEnv (or targetEnv), afterSwitch, afterSwitchProfiles — see README.

Examples:
  {$bin} {$save} staging
  {$bin} {$save} --name staging --from .env.staging
  {$bin} {$use} staging
  {$bin} {$diff} --baseline=local --env local=.env --env prod=.env.prod

TXT;
    }
}
