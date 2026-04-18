<?php

declare(strict_types=1);

namespace Devkit\Env\Cli;

use Devkit\Env\Branding;

/**
 * {@see self::BINARY} is the Composer bin / basename. {@see self::VENDOR_BIN} is what we print in help
 * and hints so copy-paste works after `composer require` (project-local `vendor/bin`).
 */
final class CliProgramName
{
    public const string BINARY = Branding::CLI_BINARY;

    /** Typical invocation when the package is installed via Composer (`./vendor/bin/…`). */
    public const string VENDOR_BIN = './vendor/bin/' . Branding::CLI_BINARY;
}
