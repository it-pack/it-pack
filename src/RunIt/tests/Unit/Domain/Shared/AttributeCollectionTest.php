<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use Codeception\Test\Unit;
use ItPack\RunIt\Behavior\Domain\AttributeValidator;
use ItPack\RunIt\Behavior\Domain\BoundedKeyGuard;
use ItPack\RunIt\Behavior\Domain\BoundedPrimitiveValueWalker;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Exception\InvalidAttributeException;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use stdClass;

#[CoversClass(AttributeCollection::class)]
#[UsesClass(AttributeValidator::class)]
#[UsesClass(BoundedKeyGuard::class)]
#[UsesClass(BoundedPrimitiveValueWalker::class)]
#[UsesClass(InvalidAttributeException::class)]
#[UsesClass(SecurityLimits::class)]
final class AttributeCollectionTest extends Unit
{
    public function testEmptyCollectionReportsEmpty(): void
    {
        $collection = AttributeCollection::empty();

        self::assertInstanceOf(AttributeCollectionInterface::class, $collection);
        self::assertTrue($collection->isEmpty());
        self::assertCount(0, $collection);
        self::assertSame([], $collection->toArray());
    }

    public function testFromArrayStoresAttributesAndProjectsDeterministically(): void
    {
        $collection = AttributeCollection::fromArray([
            'http.status' => 200,
            'duration_ms' => 12.5,
            'cached' => true,
            'label' => 'config',
            'note' => null,
            'tags' => ['a', 'b', 'c'],
            'counts' => ['read' => 1, 'write' => 2],
        ]);

        self::assertFalse($collection->isEmpty());
        self::assertCount(7, $collection);
        self::assertSame(200, $collection->get('http.status'));
        self::assertSame(12.5, $collection->get('duration_ms'));
        self::assertTrue($collection->get('cached'));
        self::assertSame('config', $collection->get('label'));
        self::assertNull($collection->get('note'));
        self::assertSame(['a', 'b', 'c'], $collection->get('tags'));
        self::assertSame(['read' => 1, 'write' => 2], $collection->get('counts'));
        self::assertTrue($collection->has('http.status'));
        self::assertFalse($collection->has('missing'));
    }

    public function testToArrayIsSortedForDeterministicSerialization(): void
    {
        $collection = AttributeCollection::fromArray([
            'zeta' => 1,
            'alpha' => 2,
            'middle' => 3,
        ]);

        self::assertSame(['alpha' => 2, 'middle' => 3, 'zeta' => 1], $collection->toArray());
    }

    public function testNestedPrimitiveValuesArePreserved(): void
    {
        $nested = ['outer' => ['inner' => 42]];
        $collection = AttributeCollection::empty()->with('nested', $nested);

        self::assertSame($nested, $collection->get('nested'));
        self::assertSame(['nested' => $nested], $collection->toArray());
    }

    public function testWithReturnsANewCollectionAndLeavesTheOriginalUnchanged(): void
    {
        $original = AttributeCollection::empty();
        $updated = $original->with('http.status', 201);

        self::assertTrue($original->isEmpty());
        self::assertSame(201, $updated->get('http.status'));
        self::assertCount(1, $updated);
        self::assertNotSame($original, $updated);
    }

    public function testWithReplacesAnExistingKey(): void
    {
        $collection = AttributeCollection::empty()
            ->with('http.status', 200)
            ->with('http.status', 404)
        ;

        self::assertSame(404, $collection->get('http.status'));
        self::assertCount(1, $collection);
    }

    public function testWithoutReturnsANewCollectionAndIsIdempotentForAbsentKeys(): void
    {
        $collection = AttributeCollection::empty()->with('http.status', 200);
        $removed = $collection->without('http.status');
        $again = $removed->without('http.status');

        self::assertTrue($removed->isEmpty());
        self::assertSame($removed, $again);
        self::assertSame(200, $collection->get('http.status'));
    }

    public function testCollectionIsIterable(): void
    {
        $collection = AttributeCollection::fromArray(['a' => 1, 'b' => 2]);

        $seen = [];
        foreach ($collection as $key => $value) {
            $seen[$key] = $value;
        }

        self::assertSame(['a' => 1, 'b' => 2], $seen);
    }

    public function testEmptyKeyIsRejected(): void
    {
        $this->expectException(InvalidAttributeException::class);

        AttributeCollection::fromArray(['' => 'value']);
    }

    public function testOversizedKeyIsRejected(): void
    {
        $this->expectException(InvalidAttributeException::class);

        AttributeCollection::fromArray([str_repeat('a', 257) => 'value']);
    }

    public function testSymbolsInKeyAreRejected(): void
    {
        $this->expectException(InvalidAttributeException::class);

        AttributeCollection::fromArray(['bad key!' => 'value']);
    }

    public function testTooManyAttributesAreRejected(): void
    {
        $attributes = [];
        for ($index = 0; $index <= SecurityLimits::default()->maxAttributeCount; ++$index) {
            $attributes['key-' . $index] = $index;
        }

        $this->expectException(InvalidAttributeException::class);

        AttributeCollection::fromArray($attributes);
    }

    public function testObjectValueIsRejected(): void
    {
        $this->expectException(InvalidAttributeException::class);

        // @phpstan-ignore argument.type (Deliberate invalid value to assert rejection.)
        AttributeCollection::fromArray(['obj' => new stdClass()]);
    }

    public function testResourceValueIsRejected(): void
    {
        $this->expectException(InvalidAttributeException::class);

        $resource = fopen('php://memory', 'rb');
        // @phpstan-ignore argument.type (Deliberate invalid value to assert rejection.)
        AttributeCollection::fromArray(['res' => $resource]);
    }

    public function testNestedObjectValueIsRejected(): void
    {
        $this->expectException(InvalidAttributeException::class);

        // @phpstan-ignore argument.type (Deliberate invalid value to assert rejection.)
        AttributeCollection::fromArray(['list' => [new stdClass()]]);
    }
}
