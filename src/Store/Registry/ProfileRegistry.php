<?php

declare(strict_types=1);

namespace Devkit\Env\Store\Registry;

use JsonException;
use RuntimeException;

/**
 * JSON registry mapping profile display names to filenames inside the store directory.
 *
 * @phpstan-type RegistryData array{version: int, profiles: array<string, string>}
 *
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 */
final class ProfileRegistry
{
    private const int VERSION = 1;

    /**
     * @param array<string, string> $profiles profileName => filename (e.g. "staging.env")
     */
    public function __construct(
        private readonly string $registryPath,
        private array $profiles = [],
    ) {
    }

    public static function load(string $registryPath): self
    {
        if (!is_readable($registryPath)) {
            return new self($registryPath, []);
        }

        $raw = file_get_contents($registryPath);
        if ($raw === false || $raw === '') {
            return new self($registryPath, []);
        }

        try {
            /** @var mixed $data */
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new self($registryPath, []);
        }

        if (!is_array($data) || !isset($data['profiles']) || !is_array($data['profiles'])) {
            return new self($registryPath, []);
        }

        /** @var array<string, string> $profiles */
        $profiles = [];
        foreach ($data['profiles'] as $name => $file) {
            if (is_string($name) && is_string($file)) {
                $profiles[$name] = $file;
            }
        }

        return new self($registryPath, $profiles);
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->profiles;
    }

    public function has(string $name): bool
    {
        return isset($this->profiles[$name]);
    }

    public function filenameFor(string $name): ?string
    {
        return $this->profiles[$name] ?? null;
    }

    public function set(string $name, string $filename): void
    {
        $this->profiles[$name] = $filename;
    }

    public function remove(string $name): void
    {
        unset($this->profiles[$name]);
    }

    public function save(): void
    {
        $dir = dirname($this->registryPath);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Cannot create directory: %s', $dir));
        }

        $payload = [
            'version' => self::VERSION,
            'profiles' => $this->profiles,
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

        if (file_put_contents($this->registryPath, $json, LOCK_EX) === false) {
            throw new RuntimeException(sprintf('Cannot write registry: %s', $this->registryPath));
        }
    }
}
