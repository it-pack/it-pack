<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Core\ConfigurationDocument;
use ItPack\ConfigureIt\ValueObject\Format;

class ConfigurationDocumentTest extends Unit
{
    public function testExposesDataOriginAndFormat(): void
    {
        $document = new ConfigurationDocument(['a' => 1], Format::JSON, 'config/app.json');

        verify($document->toArray())->equals(['a' => 1]);
        verify($document->origin())->equals('config/app.json');
        verify($document->format())->equals(Format::JSON);
    }

    public function testOriginDefaultsToNull(): void
    {
        $document = new ConfigurationDocument([], Format::PHP);

        verify($document->origin())->null();
    }
}
