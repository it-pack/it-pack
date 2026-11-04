<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Option;

use ItPack\CommitIt\Domain\Value\ConfigScope;

/**
 * Immutable configuration for {@see \ItPack\CommitIt\Contract\RepositoryFactory::init()}.
 *
 * @api
 */
final readonly class InitOptions
{
    /**
     * @param array<ConfigScope, array<string, string>> $config Configuration applied after init,
     *   keyed by scope, then by config key (e.g. `ConfigScope::Local => ['user.name' => 'Alice']`).
     */
    private function __construct(
        public ?string        $initialBranch,
        public bool           $bare,
        /** @var array<string, array<string, string>> */
        public readonly array $config = [],
    ) {
    }

    public static function default(): self
    {
        return new self(null, false);
    }

    public function withInitialBranch(string $branch): self
    {
        return new self($branch, $this->bare, $this->config);
    }

    public function withBare(bool $bare = true): self
    {
        return new self($this->initialBranch, $bare, $this->config);
    }

    /**
     * @param array<string, string> $config Configuration key/value pairs (e.g. `['user.name' => 'Alice']`).
     */
    public function withConfig(array $config, ConfigScope $scope = ConfigScope::Local): self
    {
        return new self(
            $this->initialBranch,
            $this->bare,
            ConfigScopeSet::withConfig($this->config, $config, $scope),
        );
    }
}
