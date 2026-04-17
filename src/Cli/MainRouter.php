<?php

declare(strict_types=1);

namespace Devkit\Env\Cli;

use Devkit\Env\ProjectLayout;

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

        if ($first === CliCommandName::DELETE || $first === CliCommandName::DELETE_ALIAS) {
            return (new DeleteCommand())->run(array_slice($argv, 1));
        }

        if ($first === CliCommandName::MERGE) {
            return (new MergeCommand())->run(array_slice($argv, 1));
        }

        $bin = CliProgramName::BINARY;
        fwrite(STDERR, sprintf("Unknown command: %s\nRun %s --help\n", $first, $bin));

        return self::EXIT_ERROR;
    }

    private function printGlobalHelp(): void
    {
        $bin = CliProgramName::BINARY;
        $diff = CliCommandName::DIFF;
        $merge = CliCommandName::MERGE;
        $save = CliCommandName::SAVE;
        $use = CliCommandName::USE;
        $list = CliCommandName::LIST;
        $delete = CliCommandName::DELETE;
        $deleteAlias = CliCommandName::DELETE_ALIAS;
        $config = ProjectLayout::CONFIG_FILE;
        echo <<<TXT
{$bin} — switch between saved .env profiles and compare environments.

Commands:
  {$diff}    Compare .env files (drift report). Run: {$bin} {$diff} --help
  {$merge}   Merge two .env files (interactive or --prefer). Run: {$bin} {$merge} --help
  {$save}    Save a .env file into a named profile under ./env/ (copy from --from or current target)
  {$use}     Apply a named profile over your working .env (with backup by default)
  {$list}    List saved profile names
  {$delete}  Remove a saved profile (alias: {$deleteAlias})

Configuration (optional): {$config} in the project root
  storeDir, backupDir, defaultEnv (or targetEnv), afterSwitch, afterSwitchProfiles — see README.

Examples:
  {$bin} {$save} --name staging --from .env.staging
  {$bin} {$use} staging
  {$bin} {$diff} --baseline=local --env local=.env --env prod=.env.prod

TXT;
    }
}
