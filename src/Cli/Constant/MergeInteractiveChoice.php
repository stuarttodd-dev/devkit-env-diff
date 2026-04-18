<?php

declare(strict_types=1);

namespace Devkit\Env\Cli\Constant;

/**
 * Single-character answers for interactive merge prompts (after lowercasing input).
 */
final class MergeInteractiveChoice
{
    public const string LEFT = 'l';

    public const string RIGHT = 'r';

    public const string QUIT = 'q';

    public const string YES = 'y';

    public const string NO = 'n';

    public const string EMPTY_ACCEPT_DEFAULT = '';
}
