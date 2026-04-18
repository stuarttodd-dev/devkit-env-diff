<?php

declare(strict_types=1);

namespace Devkit\Env\Store\Runner;

use Symfony\Component\Process\Process;

/**
 * Runs shell commands from the project root after a successful profile switch.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
final class PostSwitchCommandRunner
{
    /**
     * @param list<string> $commands
     */
    public function run(string $workingDirectory, array $commands): int
    {
        $total = count($commands);
        foreach ($commands as $index => $command) {
            $step = $index + 1;
            echo sprintf("Post-switch [%d/%d]: %s\n", $step, $total, $command);

            $process = Process::fromShellCommandline($command, $workingDirectory);
            $process->setTimeout(null);
            $process->run(function (string $type, string $buffer): void {
                if ($type === Process::ERR) {
                    fwrite(STDERR, $buffer);

                    return;
                }

                echo $buffer;
            });

            if (!$process->isSuccessful()) {
                $code = $process->getExitCode() ?? 1;
                fwrite(STDERR, sprintf("Post-switch command failed (exit %d).\n", $code));

                return $code;
            }
        }

        return 0;
    }
}
