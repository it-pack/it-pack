<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Context;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Exception\InvalidContextValueException;
use ItPack\RunIt\Domain\Context\ContextClassification;
use ItPack\RunIt\Domain\Context\ContextEntry;
use ItPack\RunIt\Domain\Context\ContextKey;
use ItPack\RunIt\Domain\Context\ContextSource;
use ItPack\RunIt\Domain\Shared\Instant;
use stdClass;
use TypeError;

final class ContextEntryTest extends Unit
{
    public function testPublicNamedConstructorSetsClassificationAndValue(): void
    {
        $entry = ContextEntry::public(ContextKey::fromString('tenant.id'), 'acme');

        self::assertSame('tenant.id', $entry->key()->toString());
        self::assertSame('acme', $entry->value());
        self::assertSame(ContextClassification::Public, $entry->classification());
        self::assertNull($entry->expiresAt());
        self::assertNull($entry->source());
    }

    public function testEveryClassificationHasANamedConstructor(): void
    {
        self::assertSame(
            ContextClassification::Internal,
            ContextEntry::internal(ContextKey::fromString('a'), 1)->classification(),
        );
        self::assertSame(
            ContextClassification::Confidential,
            ContextEntry::confidential(ContextKey::fromString('b'), 2)->classification(),
        );
        self::assertSame(
            ContextClassification::Restricted,
            ContextEntry::restricted(ContextKey::fromString('c'), 3)->classification(),
        );
        self::assertSame(
            ContextClassification::Secret,
            ContextEntry::secret(ContextKey::fromString('d'), 4)->classification(),
        );
    }

    public function testMetadataIsCaptured(): void
    {
        $expiry = Instant::of(2000);
        $entry = ContextEntry::public(
            ContextKey::fromString('k'),
            'v',
            $expiry,
            ContextSource::fromString('header'),
        );

        self::assertSame(2000, $entry->expiresAt()?->epochSeconds());
        self::assertSame('header', $entry->source()?->toString());
    }

    public function testIsExpiredUsesExpiry(): void
    {
        $expired = ContextEntry::public(ContextKey::fromString('k'), 'v', Instant::of(1000));
        $valid = ContextEntry::public(ContextKey::fromString('k'), 'v', Instant::of(5000));

        self::assertTrue($expired->isExpired(Instant::of(2000)));
        self::assertFalse($valid->isExpired(Instant::of(2000)));
        self::assertFalse($valid->isExpired(Instant::of(1000)));
    }

    public function testToArrayIsDeterministic(): void
    {
        $entry = ContextEntry::confidential(ContextKey::fromString('tenant.id'), 42);

        self::assertSame(
            [
                'key' => 'tenant.id',
                'value' => 42,
                'classification' => 'confidential',
                'expires_at' => null,
                'source' => null,
            ],
            $entry->toArray(),
        );
    }

    public function testObjectValueIsRejected(): void
    {
        $this->expectException(TypeError::class);

        // @phpstan-ignore argument.type (Deliberate object value to assert rejection.)
        ContextEntry::public(ContextKey::fromString('k'), new stdClass());
    }

    public function testResourceValueIsRejected(): void
    {
        $this->expectException(TypeError::class);

        $resource = fopen('php://memory', 'rb');
        // @phpstan-ignore argument.type (Deliberate resource value to assert rejection.)
        ContextEntry::public(ContextKey::fromString('k'), $resource);
    }

    public function testNestedObjectValueIsRejectedByValidator(): void
    {
        $this->expectException(InvalidContextValueException::class);

        // @phpstan-ignore argument.type (Deliberate nested object value to assert rejection.)
        ContextEntry::public(ContextKey::fromString('k'), ['nested' => new stdClass()]);
    }

    public function testOversizedStringValueIsRejected(): void
    {
        $this->expectException(InvalidContextValueException::class);

        ContextEntry::public(ContextKey::fromString('k'), str_repeat('a', 4097));
    }
}
