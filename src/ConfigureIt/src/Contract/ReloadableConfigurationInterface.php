<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

/**
 * Hot-reloadable configuration consumer surface.
 *
 * Extracted from {@see \ItPack\ConfigureIt\Core\ReloadableConfiguration} so
 * long-lived applications can depend on the contract (snapshot accessors +
 * reload/watch/stop lifecycle) instead of the concrete (ADR-0008).
 */
interface ReloadableConfigurationInterface
{
    public function snapshot(): ConfigurationSnapshotInterface;

    public function get(string $path, mixed $default = null): mixed;

    public function has(string $path): bool;

    public function exists(string $path): bool;

    public function int(string $path, ?int $default = null): int;

    public function float(string $path, ?float $default = null): float;

    public function bool(string $path, ?bool $default = null): bool;

    public function string(string $path, ?string $default = null): string;

    /**
     * @param array<int, mixed>|null $default
     * @return array<int, mixed>
     */
    public function list(string $path, ?array $default = null): array;

    /**
     * @param array<int, string>|null $default
     * @return array<int, string>
     */
    public function stringList(string $path, ?array $default = null): array;

    /**
     * @return array<string, mixed>
     */
    public function all(): array;

    public function reload(): void;

    public function watch(): void;

    public function stop(): void;
}
