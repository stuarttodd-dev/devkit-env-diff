<?php

declare(strict_types=1);

namespace Devkit\Env\Store;

use Devkit\Env\ProjectLayout;
use JsonException;

/**
 * Project-local paths for the env store (relative to working directory unless absolute).
 *
 * @phpstan-type ProfileCommands array<string, list<string>>
 *
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 */
final readonly class ProjectConfig
{
    /**
     * @param list<string>       $afterSwitchCommands   Run after every successful `use`
     * @param ProfileCommands   $afterSwitchByProfile  Extra commands per profile name
     *
     * $targetEnvPath is the default active env file (JSON keys: defaultEnv or targetEnv).
     * Override per run: use --target …, save --from … (see CLI).
     */
    public function __construct(
        public string $workingDirectory,
        public string $storeDirectory,
        public string $backupDirectory,
        public string $targetEnvPath,
        public array $afterSwitchCommands = [],
        public array $afterSwitchByProfile = [],
    ) {
    }

    public static function load(string $workingDirectory): self
    {
        $path = $workingDirectory . '/' . ProjectLayout::CONFIG_FILE;
        $defaults = new self($workingDirectory, 'env', 'env/backups', ProjectLayout::DEFAULT_ENV_FILE, [], []);

        if (!is_readable($path)) {
            return $defaults;
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return $defaults;
        }

        try {
            /** @var mixed $data */
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $defaults;
        }

        if (!is_array($data)) {
            return $defaults;
        }

        $store = isset($data['storeDir']) && is_string($data['storeDir'])
            ? $data['storeDir']
            : $defaults->storeDirectory;
        $backup = isset($data['backupDir']) && is_string($data['backupDir'])
            ? $data['backupDir']
            : $defaults->backupDirectory;
        $target = self::parseDefaultEnvPath($data, $defaults->targetEnvPath);

        $afterGlobal = self::parseStringList($data['afterSwitch'] ?? null);
        $byProfile = self::parseProfileCommands($data['afterSwitchProfiles'] ?? null);

        return new self($workingDirectory, $store, $backup, $target, $afterGlobal, $byProfile);
    }

    /**
     * defaultEnv (preferred) or targetEnv (alias) — if both are strings, targetEnv wins.
     *
     * @param array<string, mixed> $data
     */
    private static function parseDefaultEnvPath(array $data, string $fallback): string
    {
        $fromTarget = $data['targetEnv'] ?? null;
        if (is_string($fromTarget) && $fromTarget !== '') {
            return $fromTarget;
        }

        $fromDefault = $data['defaultEnv'] ?? null;
        if (is_string($fromDefault) && $fromDefault !== '') {
            return $fromDefault;
        }

        return $fallback;
    }

    /**
     * Global commands plus any defined for this profile (global first, then profile-specific).
     *
     * @return list<string>
     */
    public function commandsAfterSwitchForProfile(string $profileName): array
    {
        $out = $this->afterSwitchCommands;
        if (isset($this->afterSwitchByProfile[$profileName])) {
            foreach ($this->afterSwitchByProfile[$profileName] as $cmd) {
                $out[] = $cmd;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function parseStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $list = [];
        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $list[] = $item;
            }
        }

        return $list;
    }

    /**
     * @return ProfileCommands
     */
    private static function parseProfileCommands(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        /** @var ProfileCommands $map */
        $map = [];
        foreach ($value as $profileName => $commands) {
            if (!is_string($profileName)) {
                continue;
            }

            if ($profileName === '') {
                continue;
            }

            $map[$profileName] = self::parseStringList($commands);
        }

        return $map;
    }

    public function storeRootAbsolute(): string
    {
        return $this->absolutePath($this->storeDirectory);
    }

    public function backupRootAbsolute(): string
    {
        return $this->absolutePath($this->backupDirectory);
    }

    public function targetEnvAbsolute(): string
    {
        return $this->absolutePath($this->targetEnvPath);
    }

    public function registryAbsolutePath(): string
    {
        return $this->storeRootAbsolute() . '/registry.json';
    }

    private function absolutePath(string $path): string
    {
        if ($path === '' || $path[0] === DIRECTORY_SEPARATOR || (strlen($path) > 2 && $path[1] === ':')) {
            return $path;
        }

        return $this->workingDirectory . '/' . $path;
    }

    /**
     * @return list<string> Patterns suitable for .gitignore (leading slash = repo root).
     */
    public function gitignorePatterns(): array
    {
        $patterns = [];

        $store = trim(str_replace('\\', '/', $this->storeDirectory), '/');
        if ($store !== '') {
            $patterns[] = '/' . $store . '/';
        }

        $backup = trim(str_replace('\\', '/', $this->backupDirectory), '/');
        if ($backup !== '' && !str_starts_with($backup, $store . '/')) {
            $patterns[] = '/' . $backup . '/';
        }

        return array_values(array_unique($patterns));
    }
}
