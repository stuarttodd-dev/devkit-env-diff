<?php

declare(strict_types=1);

namespace Devkit\Env\Cli\Constant;

/**
 * Flags shared across subcommands.
 */
final class CliGlobalOption
{
    public const string HELP_SHORT = '-h';

    public const string HELP_LONG = '--help';

    public const string NO_INTERACTION_SHORT = '-n';

    public const string NO_INTERACTION_LONG = '--no-interaction';
}
