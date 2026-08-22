<?php

declare(strict_types=1);

namespace ItPack\ConfigureItTesting\Tests;

use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureItTesting\Fake\FakeLoader;
use PHPUnit\Framework\TestCase;

final class FakeLoaderTest extends TestCase
{
    public function testSupportsAllFormatsByDefault(): void
    {
        $loader = new FakeLoader();

        self::assertTrue($loader->supports(Format::JSON));
        self::assertTrue($loader->supports(Format::YAML));
    }

    public function testSupportsRestrictedFormats(): void
    {
        $loader = new FakeLoader(null, Format::JSON);

        self::assertTrue($loader->supports(Format::JSON));
        self::assertFalse($loader->supports(Format::YAML));
    }

    public function testLoadParsesJsonByDefault(): void
    {
        $document = (new FakeLoader())->load('{"name":"it"}');

        self::assertSame(['name' => 'it'], $document->toArray());
        self::assertSame(Format::PHP, $document->format());
    }

    public function testLoadWithCustomParser(): void
    {
        $loader = new FakeLoader(
            static fn (string $raw): array => ['line' => $raw],
            Format::ENV,
        );

        $document = $loader->load('HELLO');

        self::assertSame(['line' => 'HELLO'], $document->toArray());
    }
}
