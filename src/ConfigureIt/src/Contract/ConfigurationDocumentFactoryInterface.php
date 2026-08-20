<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\ValueObject\Format;

interface ConfigurationDocumentFactoryInterface
{
    public function build(
        array $data,
        Format $format,
        ?string $origin = null,
    ): ConfigurationDocumentInterface;
}