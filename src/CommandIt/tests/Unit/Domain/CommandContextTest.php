<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Codeception\Test\Unit;
use ItPack\CommandIt\Contract\Domain\Classification;
use ItPack\CommandIt\Domain\CommandContext;

/**
 * @covers \ItPack\CommandIt\Contract\Domain\Classification
 * @covers \ItPack\CommandIt\Domain\CommandContext
 */
final class CommandContextTest extends Unit
{
    public function testWithReturnsNewImmutableContext(): void
    {
        $base = new CommandContext();
        $next = $base->with('token', 'abc', Classification::Secret);

        self::assertFalse($base->has('token'), 'receiver unchanged');
        self::assertTrue($next->has('token'));
        self::assertSame('abc', $next->get('token'));
        $classification = $next->classification('token');
        self::assertInstanceOf(Classification::class, $classification);
        self::assertTrue($classification->isSecret());
    }

    public function testRedactedHidesSecrets(): void
    {
        $context = new CommandContext()
            ->with('user', 'alice', Classification::Public)
            ->with('password', 'hunter2', Classification::Secret)
        ;

        $redacted = $context->redacted();

        self::assertSame('alice', $redacted['user']);
        self::assertSame('***', $redacted['password']);
    }

    public function testRedactedUsesClassificationConstant(): void
    {
        self::assertSame('***', \ItPack\CommandIt\Contract\Domain\CommandContextInterface::REDACTED);

        $context = new CommandContext()->with('password', 'hunter2', Classification::Secret);
        $redacted = $context->redacted();

        self::assertSame(\ItPack\CommandIt\Contract\Domain\CommandContextInterface::REDACTED, $redacted['password']);
    }

    public function testEntriesStructure(): void
    {
        $context = new CommandContext()->with('k', 1, Classification::Internal);

        self::assertSame(1, $context->entries()['k']['value']);
        self::assertSame(Classification::Internal, $context->entries()['k']['classification']);
    }

    public function testMalformedEntryThrowsAtConstruction(): void
    {
        $this->expectException(\ItPack\CommandIt\Contract\Exception\InvalidCommandException::class);
        $this->expectExceptionMessage('Invalid context entry for key "token".');

        $context = new CommandContext(['token' => ['value' => 'abc']]);
        self::fail('Expected ' . \ItPack\CommandIt\Contract\Exception\InvalidCommandException::class . ' for a malformed entry.');
    }

    public function testRedactedEntriesHidesSecretsSymmetrically(): void
    {
        $context = new CommandContext()
            ->with('user', 'alice', Classification::Public)
            ->with('password', 'hunter2', Classification::Secret)
        ;

        $redacted = $context->redactedEntries();

        self::assertSame('alice', $redacted['user']);
        self::assertSame('***', $redacted['password']);
        self::assertSame($context->redacted(), $redacted, 'redactedEntries mirrors redacted()');
        self::assertSame('hunter2', $context->entries()['password']['value'], 'raw entries still expose secret');
    }

    public function testClassificationFlags(): void
    {
        self::assertTrue(Classification::Secret->isSecret());
        self::assertFalse(Classification::Public->isSecret());
        self::assertSame('secret', Classification::Secret->label());
    }
}
