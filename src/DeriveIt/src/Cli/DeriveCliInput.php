<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Cli;

use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\DeriveMode;

/**
 * Parsed, validated input for {@see DeriveCommand}. Immutable: {@see withMode}
 * returns a fresh copy so the parser can normalize (e.g. promote --root to family).
 *
 * @internal
 */
final readonly class DeriveCliInput
{
    /**
     * @param list<string> $targets
     * @param array<string, string> $namespacePrefixMap
     * @param array<string, string> $wrapperMap
     */
    public function __construct(
        public DeriveMode $mode,
        public ?string $out,
        public ?string $root,
        public array $targets,
        public DeriveConfig $config,
        public array $namespacePrefixMap,
        public array $wrapperMap,
    ) {
    }

    public function withMode(DeriveMode $mode): self
    {
        return new self(
            mode: $mode,
            out: $this->out,
            root: $this->root,
            targets: $this->targets,
            config: $this->config,
            namespacePrefixMap: $this->namespacePrefixMap,
            wrapperMap: $this->wrapperMap,
        );
    }
}
