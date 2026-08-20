<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\DotenvSource;
use ItPack\ConfigureIt\ValueObject\ConfigurationResource;
use ItPack\ConfigureIt\ValueObject\Format;

class DotenvSourceTest extends Unit
{
    public function testDiscoversResourceForExistingFile(): void
    {
        $path = sys_get_temp_dir() . '/cfg-dotenv-' . uniqid() . '.env';
        file_put_contents($path, "APP_NAME=test\n");

        try {
            $source = new DotenvSource($path);
            $resources = iterator_to_array($source->discover());

            verify(count($resources))->equals(1);
            verify($resources[0] instanceof ConfigurationResource)->true();
            verify($resources[0]->format())->equals(Format::DOTENV);
            verify($resources[0]->uri())->equals($path);
        } finally {
            unlink($path);
        }
    }

    public function testYieldsNothingWhenFileMissing(): void
    {
        $source = new DotenvSource('/no/such/file.env');

        verify(iterator_to_array($source->discover()))->equals([]);
    }
}
