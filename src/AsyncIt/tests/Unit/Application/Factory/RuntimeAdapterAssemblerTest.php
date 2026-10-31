<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Factory;

use ItPack\AsyncIt\Application\Context\MemoryContextStorage;
use ItPack\AsyncIt\Application\Factory\RuntimeAdapterAssembler;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\CapabilityKind;
use ItPack\AsyncIt\Contract\Port\CapabilityStatus;
use ItPack\AsyncIt\Contract\Port\RuntimeAdapterKind;
use ItPack\AsyncIt\Domain\CapabilityDescriptor;
use ItPack\AsyncIt\Domain\Channel\EmulatedChannel;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;

/**
 * @covers \ItPack\AsyncIt\Application\Factory\RuntimeAdapterAssembler
 */
final class RuntimeAdapterAssemblerTest extends TestCase
{
    public function testAssembleReferenceReturnsReferencePrimitives(): void
    {
        $assembler = new RuntimeAdapterAssembler();
        $descriptor = new CapabilityDescriptor([
            CapabilityKind::EdgeTriggeredPolling->value => CapabilityStatus::Emulated,
        ]);

        $assembly = $assembler->assemble(RuntimeAdapterKind::Reference, $descriptor);

        self::assertInstanceOf(PollerPortInterface::class, $assembly->poller);
        self::assertInstanceOf(MemoryContextStorage::class, $assembly->storage);
        self::assertInstanceOf(EmulatedChannel::class, $assembly->channel);
    }

    public function testAssembleSwooleReturnsSwoolePrimitives(): void
    {
        if (!class_exists(Coroutine::class)) {
            self::markTestSkipped('Swoole extension not available.');
        }

        $assembler = new RuntimeAdapterAssembler();
        $descriptor = new CapabilityDescriptor([
            CapabilityKind::EdgeTriggeredPolling->value => CapabilityStatus::Supported,
        ]);

        $assembly = $assembler->assemble(RuntimeAdapterKind::Swoole, $descriptor);

        self::assertInstanceOf(PollerPortInterface::class, $assembly->poller);
        self::assertInstanceOf(ContextStorageInterface::class, $assembly->storage);
    }
}
