<?php

declare(strict_types=1);

namespace Devkit\Env;

/**
 * Default filenames and paths referenced across CLI and store.
 */
final class ProjectLayout
{
    public const string CONFIG_FILE = '.devkit-env.json';

    /** Default env file name in the project root (see {@see \Devkit\Env\Store\ProjectConfig}). */
    public const string DEFAULT_ENV_FILE = '.env';
}
