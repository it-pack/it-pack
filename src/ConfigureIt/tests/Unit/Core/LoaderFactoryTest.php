<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Contract\ConfigurationDocumentInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface;
use ItPack\ConfigureIt\Core\ConfigurationLoaderFactory;
use ItPack\ConfigureIt\Exception\ResolutionException;
use ItPack\ConfigureIt\ValueObject\Format;
use LogicException;

final class StubLoader implements ConfigurationLoaderInterface
{
    public function __construct(
        private Format $format,
    ) {
    }

    public function supports(Format $format): bool
    {
        return $format === $this->format;
    }

    public function load(string $raw): ConfigurationDocumentInterface
    {
        throw new LogicException('not used in this test');
    }
}

class LoaderFactoryTest extends Unit
{
    public function testReturnsSupportingLoader(): void
    {
        $factory = new ConfigurationLoaderFactory([
            new StubLoader(Format::PHP),
            new StubLoader(Format::JSON),
        ]);

        verify($factory->build(Format::JSON) instanceof StubLoader)->true();
    }

    public function testFirstSupportingLoaderWins(): void
    {
        $winner = new StubLoader(Format::YAML);
        $loser = new StubLoader(Format::YAML);

        $factory = new ConfigurationLoaderFactory([$winner, $loser]);

        verify($factory->build(Format::YAML))->same($winner);
    }

    public function testThrowsWhenNoLoaderSupportsFormat(): void
    {
        $factory = new ConfigurationLoaderFactory([new StubLoader(Format::JSON)]);

        $this->expectException(ResolutionException::class);

        $factory->build(Format::YAML);
    }
}
