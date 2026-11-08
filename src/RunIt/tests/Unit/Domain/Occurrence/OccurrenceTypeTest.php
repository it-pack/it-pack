<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Occurrence;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\OccurrenceTypeInterface;
use ItPack\RunIt\Contract\Exception\InvalidStringValueException;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(OccurrenceType::class)]
final class OccurrenceTypeTest extends Unit
{
    /**
     * Values that satisfy the occurrence-type format.
     *
     * @return array<string, array{string, string}>
     */
    public static function acceptedProvider(): array
    {
        return [
            'namespaced v1' => ['itpack.configuration.loaded.v1', '1'],
            'single segment' => ['ping.v3', '3'],
            'with dash and dot' => ['it-pack.job.processed.v12', '12'],
            'numeric prefix allowed' => ['acme.2step.done.v1', '1'],
        ];
    }

    /**
     * Values that violate the occurrence-type format.
     *
     * @return array<string, array{string}>
     */
    public static function rejectedProvider(): array
    {
        return [
            'uppercase' => ['Itpack.Configuration.Loaded.v1'],
            'missing version' => ['itpack.configuration.loaded'],
            'version not numeric' => ['itpack.configuration.loaded.vx'],
            'empty version' => ['itpack.configuration.loaded.v'],
            'space' => ['itpack configuration loaded v1'],
            'leading dot' => ['.itpack.loaded.v1'],
            'trailing dot' => ['itpack.loaded.v1.'],
            'empty segment' => ['itpack..loaded.v1'],
            'empty' => [''],
            'symbol' => ['itpack.loaded!v1'],
        ];
    }

    #[DataProvider('acceptedProvider')]
    public function testAcceptedTypesRoundTripAndExposeVersion(string $value, string $version): void
    {
        $type = OccurrenceType::fromString($value);

        self::assertInstanceOf(OccurrenceTypeInterface::class, $type);
        self::assertSame($value, $type->toString());
        self::assertSame($version, $type->version());
    }

    #[DataProvider('rejectedProvider')]
    public function testRejectedTypesThrowATypedException(string $value): void
    {
        $this->expectException(InvalidStringValueException::class);

        OccurrenceType::fromString($value);
    }

    public function testTypeAwareEqualityPreventsCrossTypeConfusion(): void
    {
        $configuration = OccurrenceType::fromString('itpack.configuration.loaded.v1');
        $snapshot = OccurrenceType::fromString('itpack.snapshot.created.v1');

        self::assertFalse($configuration->equals($snapshot));
        self::assertTrue($configuration->equals(OccurrenceType::fromString('itpack.configuration.loaded.v1')));
    }

    public function testVersionBumpProducesADistinctType(): void
    {
        $v1 = OccurrenceType::fromString('itpack.configuration.loaded.v1');
        $v2 = OccurrenceType::fromString('itpack.configuration.loaded.v2');

        self::assertSame('1', $v1->version());
        self::assertSame('2', $v2->version());
        self::assertFalse($v1->equals($v2));
    }
}
