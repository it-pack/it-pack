<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Option;

use ItPack\CommitIt\Domain\Value\ConfigScope;

/**
 * Immutable configuration for {@see \ItPack\CommitIt\Contract\RepositoryFactory::cloneRepository()}.
 *
 * @api
 */
final readonly class CloneOptions
{
    /**
     * @param array<ConfigScope, array<string, string>> $config Configuration applied after clone,
     *   keyed by scope, then by config key (e.g. `ConfigScope::Local => ['user.name' => 'Alice']`).
     */
    private function __construct(
        public ?string $branch,
        public bool    $bare,
        public ?int    $depth,
        /** @var array<string, array<string, string>> */
        public array   $config = [],
    ) {
    }

    public static function default(): self
    {
        return new self(null, false, null);
    }

    public function withBranch(?string $branch): self
    {
        return new self($branch, $this->bare, $this->depth, $this->config);
    }

    public function withBare(bool $bare = true): self
    {
        return new self($this->branch, $bare, $this->depth, $this->config);
    }

    public function withDepth(?int $depth): self
    {
        return new self($this->branch, $this->bare, $depth, $this->config);
    }

    /**
     * @param array<string, string> $config Configuration key/value pairs (e.g. `['user.name' => 'Alice']`).
     */
    public function withConfig(array $config, ConfigScope $scope = ConfigScope::Local): self
    {
        return new self(
            $this->branch,
            $this->bare,
            $this->depth,
            ConfigScopeSet::withConfig($this->config, $config, $scope),
        );
    }
}
