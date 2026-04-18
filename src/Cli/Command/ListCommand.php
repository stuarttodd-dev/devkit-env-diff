<?php

declare(strict_types=1);

namespace Devkit\Env\Cli\Command;

use Devkit\Env\Cli\CliCommandName;
use Devkit\Env\Cli\CliGlobalOption;
use Devkit\Env\Cli\CliProgramName;
use Devkit\Env\Store\Config\ProjectConfig;
use Devkit\Env\Store\Service\EnvProfileManager;

/**
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
final readonly class ListCommand
{
    private const int EXIT_OK = 0;

    /**
     * @param list<string> $argv arguments after "list"
     */
    public function run(array $argv): int
    {
        if ($argv !== [] && ($argv[0] === CliGlobalOption::HELP_SHORT || $argv[0] === CliGlobalOption::HELP_LONG)) {
            $bin = CliProgramName::VENDOR_BIN;
            $cmd = CliCommandName::LIST;
            echo <<<TXT
Usage: {$bin} {$cmd}

Print saved profile names from env/registry.json.

TXT;

            return self::EXIT_OK;
        }

        $cwd = getcwd();
        if ($cwd === false) {
            fwrite(STDERR, "Cannot determine current working directory.\n");

            return 2;
        }

        $config = ProjectConfig::load($cwd);
        $manager = new EnvProfileManager($config);
        $names = $manager->listNames();

        if ($names === []) {
            echo "(no profiles saved yet)\n";

            return self::EXIT_OK;
        }

        foreach ($names as $name) {
            echo $name . "\n";
        }

        return self::EXIT_OK;
    }
}
