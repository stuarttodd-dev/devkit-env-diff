<?php

declare(strict_types=1);

namespace Devkit\Env\Cli;

/**
 * Long options and --name= value prefixes for merge.
 */
final class MergeCliOption
{
    public const string PREFER_LONG = '--prefer';

    public const string PREFER_EQ_PREFIX = '--prefer=';

    public const string LEFT_LONG = '--left';

    public const string LEFT_EQ_PREFIX = '--left=';

    public const string RIGHT_LONG = '--right';

    public const string RIGHT_EQ_PREFIX = '--right=';

    public const string OUT_LONG = '--out';

    public const string OUT_EQ_PREFIX = '--out=';

    public const string NO_MASK = '--no-mask';

    public const string MASK_KEY_LONG = '--mask-key';

    public const string MASK_KEY_EQ_PREFIX = '--mask-key=';

    public const string DRY_RUN_LONG = '--dry-run';
}
