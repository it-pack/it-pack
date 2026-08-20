<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\ValueObject\ConfigurationResourceFactory;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Exposes a single ".env" file as a DOTENV configuration resource.
 *
 * Discovery only — never reads or parses. Implements the `ConfigurationSource`
 * port; the companion {@see FileReader} + {@see DotenvLoader} perform the read
 * and parse. When the file is absent discovery yields nothing (mirrors
 * {@see FileSource} for a missing directory).
 *
 * @see docs/architecture/MODEL.md "Configuration Source"
 */
final readonly class DotenvSource implements ConfigurationSourceInterface
{
    public function __construct(
        private string $path,
        private ConfigurationResourceFactory $resourceFactory = new ConfigurationResourceFactory(),
    ) {
    }

    /**
     * @return iterable<ConfigurationResourceInterface>
     */
    public function discover(): iterable
    {
        if (!is_file($this->path)) {
            return;
        }

        yield $this->resourceFactory->build($this->path, Format::DOTENV);
    }
}
