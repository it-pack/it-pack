<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Contract;

use ItPack\AsyncIt\Conformance\Contract\PortFixtureInterface;
use ItPack\AsyncIt\Conformance\Contract\VerifierInterface;
use ItPack\AsyncIt\Contract\SchedulerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Conformance\Contract\PortFixtureInterface
 * @covers \ItPack\AsyncIt\Conformance\Contract\VerifierInterface
 */
final class ContractInterfacesTest extends TestCase
{
    public function testPortFixtureContractIsImplementable(): void
    {
        $this->exerciseFixture(new class () implements PortFixtureInterface {
            public function portName(): string
            {
                return 'Scheduler';
            }
        });
    }

    public function testVerifierContractIsImplementable(): void
    {
        $this->exerciseVerifier(new class () implements VerifierInterface {
            public function port(): string
            {
                return SchedulerInterface::class;
            }
        });
    }

    private function exerciseFixture(PortFixtureInterface $fixture): void
    {
        self::assertSame('Scheduler', $fixture->portName());
    }

    private function exerciseVerifier(VerifierInterface $verifier): void
    {
        self::assertStringContainsString('Scheduler', $verifier->port());
    }
}
