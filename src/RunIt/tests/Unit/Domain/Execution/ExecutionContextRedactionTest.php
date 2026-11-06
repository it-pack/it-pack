<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Execution;

use ItPack\RunIt\Application\Factory\ExecutionSnapshotFactory;
use ItPack\RunIt\Contract\Domain\ExecutionContextInterface as ContextContract;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;
use ItPack\RunIt\Domain\Context\ContextEntry;
use ItPack\RunIt\Domain\Context\ContextKey;
use ItPack\RunIt\Domain\Context\DefaultContextRedactionPolicy;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Factory\StringIdentifierFactory;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use PHPUnit\Framework\TestCase;

/**
 * T0.1 (REVIEW S1): secret context must be withheld from normalized artifacts.
 *
 * The default deny-secret policy is applied at the domain boundary so the
 * projection never leaks secrets under any wiring.
 */
final class ExecutionContextRedactionTest extends TestCase
{
    private function contextWithSecret(): ContextContract
    {
        return ExecutionContext::empty()
            ->with(ContextEntry::public(ContextKey::fromString('tenant.id'), 'acme'))
            ->with(ContextEntry::secret(ContextKey::fromString('secret.token'), 's3cr3t'))
        ;
    }

    private function executionWithSecret(): Execution
    {
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('checkout'),
            ExecutionKind::fromString('http.server'),
            null,
            [],
            $this->contextWithSecret(),
        );

        return Execution::start(
            ExecutionId::fromString('exec-1'),
            $definition,
            Instant::of(1_700_000_000),
        );
    }

    public function testExecutionNormalizeWithholdsSecretContext(): void
    {
        $normalized = $this->executionWithSecret()->normalize();

        self::assertArrayHasKey('context', $normalized);
        /** @var array<string, array<string, mixed>> $context */
        $context = $normalized['context'];

        self::assertArrayHasKey('tenant.id', $context);
        self::assertArrayNotHasKey('secret.token', $context);
    }

    public function testExecutionDefinitionNormalizeWithholdsSecretContext(): void
    {
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('checkout'),
            ExecutionKind::fromString('http.server'),
            null,
            [],
            $this->contextWithSecret(),
        );

        $normalized = $definition->normalize();

        /** @var array<string, array<string, mixed>> $context */
        $context = $normalized['context'];

        self::assertArrayHasKey('tenant.id', $context);
        self::assertArrayNotHasKey('secret.token', $context);
    }

    public function testTerminalSnapshotPayloadWithholdsSecretContext(): void
    {
        $generator = new class () implements IdentifierGeneratorInterface {
            public function generate(): string
            {
                return 'snap-' . bin2hex(random_bytes(8));
            }
        };

        $factory = new ExecutionSnapshotFactory(
            new StringIdentifierFactory(SnapshotId::fromString(...), $generator),
            new DeterministicHashCalculator(),
        );

        $snapshot = $factory->terminal($this->executionWithSecret());
        /** @var array<string, mixed> $normalized */
        $normalized = $snapshot->normalize();
        /** @var array<string, mixed> $payload */
        $payload = $normalized['payload'];

        self::assertArrayHasKey('context', $payload);
        /** @var array<string, array<string, mixed>> $context */
        $context = $payload['context'];

        self::assertArrayHasKey('tenant.id', $context);
        self::assertArrayNotHasKey('secret.token', $context);
    }

    public function testExecutionContextToArrayAppliesSuppliedPolicy(): void
    {
        $redacted = $this->contextWithSecret()->toArray(new DefaultContextRedactionPolicy());

        self::assertArrayHasKey('tenant.id', $redacted);
        self::assertArrayNotHasKey('secret.token', $redacted);
    }

    public function testExecutionContextToArrayWithoutPolicyKeepsRawContext(): void
    {
        $raw = $this->contextWithSecret()->toArray();

        self::assertArrayHasKey('secret.token', $raw);
    }
}
