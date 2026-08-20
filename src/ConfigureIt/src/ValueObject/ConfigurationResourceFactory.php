<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\ValueObject;

use ItPack\ConfigureIt\Behavior\CreatableBehavior;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceFactoryInterface;

class ConfigurationResourceFactory implements ConfigurationResourceFactoryInterface
{
    use CreatableBehavior;

    public function build(string $uri, Format $format): ConfigurationResource
    {
        return new ConfigurationResource($uri, $format);
    }
}