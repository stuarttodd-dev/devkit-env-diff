<?php

declare(strict_types=1);

namespace Devkit\Env\Cli;

use Devkit\Env\Branding;

/**
 * Executable name as invoked by users (help text, errors).
 */
final class CliProgramName
{
    public const string BINARY = Branding::CLI_BINARY;
}
