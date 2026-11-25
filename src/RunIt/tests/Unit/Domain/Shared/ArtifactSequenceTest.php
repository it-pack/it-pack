<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Exception\InvalidStringValueException;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ArtifactSequence::class)]
final class ArtifactSequenceTest extends Unit
{
    public function testInitialIsZero(): void
    {
        self::assertSame(0, ArtifactSequence::initial()->value());
    }

    #[DataProvider('values')]
    public function testOfAndValue(int $value): void
    {
        self::assertSame($value, ArtifactSequence::of($value)->value());
    }

    /**
     * @return array<string, array<int>>
     */
    public static function values(): array
    {
        return [
            'zero' => [0],
            'one' => [1],
            'seven' => [7],
        ];
    }

    public function testNextIncrements(): void
    {
        self::assertSame(1, ArtifactSequence::initial()->next()->value());
        self::assertSame(8, ArtifactSequence::of(7)->next()->value());
    }

    public function testNegativeValueThrows(): void
    {
        $this->expectException(InvalidStringValueException::class);
        ArtifactSequence::of(-1);
    }
}
