<?php

declare(strict_types=1);

namespace Devkit\Env\Store\Service;

use DateTimeImmutable;
use Devkit\Env\Store\Config\ProjectConfig;
use Devkit\Env\Store\Registry\ProfileRegistry;
use Devkit\Env\Store\Validation\SourcePathDiagnostics;
use Devkit\Env\Store\ValueObject\ProfileName;
use InvalidArgumentException;
use RuntimeException;

/**
 * Save named profiles and apply them over a target .env with optional backup.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
final readonly class EnvProfileManager
{
    public function __construct(
        private ProjectConfig $config,
        private GitignoreManager $gitignore = new GitignoreManager(),
    ) {
    }

    /**
     * Copy $fromPath into the store under $name (updates registry).
     */
    public function save(string $name, string $fromPath, bool $force): void
    {
        $validated = ProfileName::validate($name);
        $why = SourcePathDiagnostics::whyNotUsableSourceFile($fromPath);
        if ($why !== null) {
            throw new InvalidArgumentException($why);
        }

        $registry = ProfileRegistry::load($this->config->registryAbsolutePath());
        $filename = ProfileName::toFilename($validated);

        if ($registry->has($validated) && !$force) {
            throw new InvalidArgumentException(
                sprintf('Profile "%s" already exists. Use --force to overwrite.', $validated)
            );
        }

        $dest = $this->config->storeRootAbsolute() . '/' . $filename;
        $this->ensureDirectory(dirname($dest));

        if (!copy($fromPath, $dest)) {
            throw new RuntimeException(sprintf('Failed to copy to %s', $dest));
        }

        $registry->set($validated, $filename);
        $registry->save();

        $this->ensureGitignore();
    }

    /**
     * Copy stored profile onto target path; optionally backup existing target first.
     */
    public function apply(string $name, bool $backup, ?string $backupDirOverride, ?string $targetOverride = null): void
    {
        $validated = ProfileName::validate($name);
        $registry = ProfileRegistry::load($this->config->registryAbsolutePath());
        $filename = $registry->filenameFor($validated);
        if ($filename === null) {
            throw new InvalidArgumentException(sprintf('Unknown profile: %s', $validated));
        }

        $source = $this->config->storeRootAbsolute() . '/' . $filename;
        if (!is_readable($source)) {
            throw new RuntimeException(sprintf('Profile file missing: %s', $source));
        }

        $target = $targetOverride !== null
            ? $this->resolvePathAgainstCwd($targetOverride)
            : $this->config->targetEnvAbsolute();
        if ($backup && is_readable($target)) {
            $backupRoot = $backupDirOverride !== null
                ? $this->resolveBackupRoot($backupDirOverride)
                : $this->config->backupRootAbsolute();
            $this->ensureDirectory($backupRoot);
            $stamp = (new DateTimeImmutable())->format('Y-m-d\THis');
            $base = basename($target);
            $backupFile = $backupRoot . '/' . $validated . '-before-' . $stamp . '-' . $base;
            if (!copy($target, $backupFile)) {
                throw new RuntimeException(sprintf('Failed to backup %s to %s', $target, $backupFile));
            }
        }

        $this->ensureDirectory(dirname($target));
        if (!copy($source, $target)) {
            throw new RuntimeException(sprintf('Failed to write %s', $target));
        }

        $this->ensureGitignore();
    }

    /**
     * @return list<string> sorted profile names
     */
    public function listNames(): array
    {
        $names = array_keys(ProfileRegistry::load($this->config->registryAbsolutePath())->all());
        sort($names);

        $this->ensureGitignore();

        return $names;
    }

    /**
     * Remove a profile from the registry and delete its file under the store directory.
     */
    public function delete(string $name): void
    {
        $validated = ProfileName::validate($name);
        $registry = ProfileRegistry::load($this->config->registryAbsolutePath());
        if (!$registry->has($validated)) {
            throw new InvalidArgumentException(sprintf('Unknown profile: %s', $validated));
        }

        $filename = $registry->filenameFor($validated);
        if ($filename === null) {
            throw new RuntimeException(sprintf('Registry inconsistency for profile: %s', $validated));
        }

        $path = $this->config->storeRootAbsolute() . '/' . $filename;
        $registry->remove($validated);
        $registry->save();

        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException(sprintf('Could not delete profile file: %s', $path));
        }

        $this->ensureGitignore();
    }

    private function ensureGitignore(): void
    {
        $this->gitignore->ensurePatterns($this->config->workingDirectory, $this->config->gitignorePatterns());
    }

    private function ensureDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Cannot create directory: %s', $dir));
        }
    }

    private function resolveBackupRoot(string $path): string
    {
        return $this->resolvePathAgainstCwd($path);
    }

    private function resolvePathAgainstCwd(string $path): string
    {
        if ($path === '' || $path[0] === DIRECTORY_SEPARATOR || (strlen($path) > 2 && $path[1] === ':')) {
            return $path;
        }

        return $this->config->workingDirectory . '/' . $path;
    }
}
