<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Context;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\ContextCollisionPolicy;
use ItPack\RunIt\Contract\Exception\InvalidContextValueException;
use ItPack\RunIt\Domain\Context\ContextEntry;
use ItPack\RunIt\Domain\Context\ContextKey;
use ItPack\RunIt\Domain\Context\ExecutionContext;

final class ExecutionContextTest extends Unit
{
    public function testEmptyContextIsEmpty(): void
    {
        $context = ExecutionContext::empty();

        self::assertCount(0, $context);
        self::assertCount(0, $context);
    }

    public function testWithIsCopyOnWriteAndLeavesOriginalUnchanged(): void
    {
        $original = ExecutionContext::empty();
        $updated = $original->with(ContextEntry::public(ContextKey::fromString('a'), 1));

        self::assertCount(0, $original);
        self::assertCount(1, $updated);
        self::assertSame(1, $updated->get('a')->value());
    }

    public function testHasFindAndGet(): void
    {
        $context = ExecutionContext::empty()->with(ContextEntry::public(ContextKey::fromString('a'), 1));

        self::assertTrue($context->has('a'));
        self::assertFalse($context->has('missing'));
        self::assertNotNull($context->find('a'));
        self::assertNull($context->find('missing'));
        self::assertSame(1, $context->get('a')->value());
    }

    public function testGetThrowsForAbsentKey(): void
    {
        $this->expectException(InvalidContextValueException::class);

        ExecutionContext::empty()->get('missing');
    }

    public function testWithoutRemovesAndIsIdempotent(): void
    {
        $context = ExecutionContext::empty()
            ->with(ContextEntry::public(ContextKey::fromString('a'), 1))
            ->without('a')
        ;

        self::assertCount(0, $context);
        self::assertSame($context, $context->without('a'));
    }

    public function testMergeReplaceWinsOnCollision(): void
    {
        $base = ExecutionContext::empty()->with(ContextEntry::public(ContextKey::fromString('a'), 1));
        $other = ExecutionContext::empty()
            ->with(ContextEntry::public(ContextKey::fromString('a'), 2))
            ->with(ContextEntry::public(ContextKey::fromString('b'), 3))
        ;

        $merged = $base->merge($other, ContextCollisionPolicy::Replace);

        self::assertSame(2, $merged->get('a')->value());
        self::assertSame(3, $merged->get('b')->value());
        self::assertCount(2, $merged);
    }

    public function testMergeKeepWinsOnCollision(): void
    {
        $base = ExecutionContext::empty()->with(ContextEntry::public(ContextKey::fromString('a'), 1));
        $other = ExecutionContext::empty()->with(ContextEntry::public(ContextKey::fromString('a'), 2));

        $merged = $base->merge($other, ContextCollisionPolicy::Keep);

        self::assertSame(1, $merged->get('a')->value());
    }

    public function testMergeThrowRaisesOnCollision(): void
    {
        $base = ExecutionContext::empty()->with(ContextEntry::public(ContextKey::fromString('a'), 1));
        $other = ExecutionContext::empty()->with(ContextEntry::public(ContextKey::fromString('a'), 2));

        $this->expectException(InvalidContextValueException::class);

        $base->merge($other, ContextCollisionPolicy::Throw);
    }

    public function testCapacityIsEnforced(): void
    {
        $context = ExecutionContext::empty();
        for ($index = 0; $index < ExecutionContext::MAX_ENTRIES; ++$index) {
            $context = $context->with(ContextEntry::public(ContextKey::fromString('k' . $index), $index));
        }

        self::assertCount(ExecutionContext::MAX_ENTRIES, $context);

        $this->expectException(InvalidContextValueException::class);

        $context->with(ContextEntry::public(ContextKey::fromString('overflow'), 1));
    }

    public function testIterationYieldsEntries(): void
    {
        $context = ExecutionContext::empty()
            ->with(ContextEntry::public(ContextKey::fromString('a'), 1))
            ->with(ContextEntry::public(ContextKey::fromString('b'), 2))
        ;

        $seen = [];
        foreach ($context->entries() as $key => $entry) {
            $seen[$key] = $entry->value();
        }

        self::assertSame(['a' => 1, 'b' => 2], $seen);
    }

    public function testToArrayIsDeterministicAndSorted(): void
    {
        $context = ExecutionContext::empty()
            ->with(ContextEntry::public(ContextKey::fromString('zeta'), 1))
            ->with(ContextEntry::public(ContextKey::fromString('alpha'), 2))
        ;

        $expected = [
            'alpha' => [
                'key' => 'alpha', 'value' => 2, 'classification' => 'public', 'expires_at' => null, 'source' => null,
            ],
            'zeta' => [
                'key' => 'zeta', 'value' => 1, 'classification' => 'public', 'expires_at' => null, 'source' => null,
            ],
        ];

        self::assertSame($expected, $context->toArray());
    }
}
