<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationDocumentFactoryInterface;
use ItPack\ConfigureIt\Contract\ConfigurationDocumentInterface;
use ItPack\ConfigureIt\ValueObject\Format;

class ConfigurationDocumentFactory implements ConfigurationDocumentFactoryInterface
{
    public function create(): self
    {
        return new self();
    }

    /**
     * @param array<mixed> $data
     */
    public function build(
        array $data,
        Format $format,
        ?string $origin = null,
        ?string $adapter = null,
        ?string $layerId = null,
    ): ConfigurationDocumentInterface {
        return new ConfigurationDocument(
            $data,
            $format,
            $origin,
            $adapter,
            $layerId,
        );
    }
}
