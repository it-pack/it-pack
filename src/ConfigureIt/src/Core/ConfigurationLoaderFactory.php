<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationLoaderFactoryInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface;
use ItPack\ConfigureIt\Exception\ResolutionException;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Default {@see ConfigurationLoaderFactoryInterface} backed by an explicit loader registry.
 *
 * Built from a set of {@see ConfigurationLoaderInterface} instances; the first
 * loader declaring {@see ConfigurationLoaderInterface::supports()} for a format
 * wins. Unknown formats raise a {@see ResolutionException}, which keeps the
 * resolver free of dispatch logic.
 *
 * @see docs/architecture/decisions/ADR-0006-Loader-Factory.md
 */
final readonly class ConfigurationLoaderFactory implements ConfigurationLoaderFactoryInterface
{
    /**
     * @param iterable<ConfigurationLoaderInterface> $loaders
     */
    public function __construct(
        private iterable $loaders,
    ) {
    }

    /**
     * @param iterable<\ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface> $loaders
     */
    public static function create(iterable $loaders): self
    {
        return new self($loaders);
    }

    public function build(Format $format): ConfigurationLoaderInterface
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($format)) {
                return $loader;
            }
        }

        throw new ResolutionException(sprintf('No loader supports format "%s".', $format->value));
    }
}
