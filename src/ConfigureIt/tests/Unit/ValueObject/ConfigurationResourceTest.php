<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObject;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\ValueObject\ConfigurationResource;
use ItPack\ConfigureIt\ValueObject\Format;

class ConfigurationResourceTest extends Unit
{
    public function testExposesUriAndFormat(): void
    {
        $resource = new ConfigurationResource('/etc/app.php', Format::PHP);

        verify($resource->uri())->equals('/etc/app.php');
        verify($resource->format())->equals(Format::PHP);
    }
}
