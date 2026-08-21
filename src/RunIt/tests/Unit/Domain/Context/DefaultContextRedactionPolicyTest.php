<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Context;

use Codeception\Test\Unit;
use ItPack\RunIt\Domain\Context\ContextEntry;
use ItPack\RunIt\Domain\Context\ContextKey;
use ItPack\RunIt\Domain\Context\DefaultContextRedactionPolicy;
use ItPack\RunIt\Domain\Context\ExecutionContext;

final class DefaultContextRedactionPolicyTest extends Unit
{
    public function testPublicEntriesAreKeptAndSensitiveEntriesAreRedactedOrDropped(): void
    {
        $context = ExecutionContext::empty()
            ->with(ContextEntry::public(ContextKey::fromString('env'), 'prod'))
            ->with(ContextEntry::internal(ContextKey::fromString('trace.id'), 't1'))
            ->with(ContextEntry::confidential(ContextKey::fromString('user.id'), 'u1'))
            ->with(ContextEntry::restricted(ContextKey::fromString('token'), 's3cr3t'))
            ->with(ContextEntry::secret(ContextKey::fromString('password'), 'hunter2'))
        ;

        $redacted = new DefaultContextRedactionPolicy()->redact($context);

        self::assertTrue($redacted->has('env'));
        self::assertSame('prod', $redacted->get('env')->value());

        self::assertFalse($redacted->has('trace.id'));
        self::assertFalse($redacted->has('user.id'));
        self::assertFalse($redacted->has('password'));

        self::assertTrue($redacted->has('token'));
        self::assertSame(
            DefaultContextRedactionPolicy::REDACTED,
            $redacted->get('token')->value(),
        );
        self::assertSame('restricted', $redacted->get('token')->classification()->label());
    }

    public function testOriginalContextIsNotMutated(): void
    {
        $context = ExecutionContext::empty()
            ->with(ContextEntry::secret(ContextKey::fromString('password'), 'hunter2'))
        ;
        $policy = new DefaultContextRedactionPolicy();

        $policy->redact($context);

        self::assertTrue($context->has('password'));
        self::assertSame('hunter2', $context->get('password')->value());
    }
}
