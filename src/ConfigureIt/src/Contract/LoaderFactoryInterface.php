<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\Exception\ResolutionException;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Resolves a {@see ConfigurationLoaderInterface} for a given {@see Format}.
 *
 * The resolver must not scan loader lists itself; that dispatch is encapsulated
 * here so that adding a supported format (YAML, TOML, NEON — ROADMAP Phase 2)
 * requires only a new adapter plus one registration, not a change to the
 * resolver (Open/Closed Principle).
 *
 * @see docs/architecture/decisions/ADR-0006-Loader-Factory.md
 */
interface LoaderFactoryInterface
{
    /**
     * @throws ResolutionException when no loader supports the format.
     */
    public function loaderFor(Format $format): ConfigurationLoaderInterface;
}
