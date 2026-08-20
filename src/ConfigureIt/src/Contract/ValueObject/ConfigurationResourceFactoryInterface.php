<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract\ValueObject;

use ItPack\ConfigureIt\ValueObject\Format;

interface ConfigurationResourceFactoryInterface
{
    public function build(string $uri, Format $format): ConfigurationResourceInterface;
}
