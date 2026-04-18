<?php

declare(strict_types=1);

namespace Devkit\Env\Cli\Constant;

/**
 * Top-level subcommand names after the program binary.
 */
final class CliCommandName
{
    public const string DIFF = 'diff';

    public const string MERGE = 'merge';

    public const string SAVE = 'save';

    public const string USE = 'use';

    public const string LIST = 'list';

    public const string DELETE = 'delete';

    public const string DELETE_ALIAS = 'rm';

    public const string HELP = 'help';
}
