<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Attribute;

use Closure;
use ItPack\RunIt\SDK\Attribute\DefaultExecutedAsMetadataReader;
use ItPack\RunIt\SDK\Attribute\ExecutedAs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultExecutedAsMetadataReader::class)]
#[CoversClass(ExecutedAs::class)]
final class ExecutedAsMetadataReaderTest extends TestCase
{
    private DefaultExecutedAsMetadataReader $reader;

    protected function setUp(): void
    {
        $this->reader = new DefaultExecutedAsMetadataReader();
    }

    public function testAttributeMetadataIsResolvedFromMethod(): void
    {
        $metadata = $this->reader->read([new SampleService(), 'run']);

        self::assertNotNull($metadata);
        self::assertSame('service.run', $metadata->name);
        self::assertSame('operation', $metadata->kind);
    }

    public function testAttributeMetadataIsResolvedFromClass(): void
    {
        $metadata = $this->reader->read([new AttributedService(), 'handle']);

        self::assertNotNull($metadata);
        self::assertSame('service.class', $metadata->name);
        self::assertSame('batch', $metadata->kind);
    }

    public function testAttributeMetadataIsResolvedFromFreeFunction(): void
    {
        $metadata = $this->reader->read('Tests\Unit\SDK\Attribute\sample_free_function');

        self::assertNotNull($metadata);
        self::assertSame('free.fn', $metadata->name);
        self::assertSame('task', $metadata->kind);
    }

    public function testAttributeMetadataIsResolvedFromClosureBoundToMethod(): void
    {
        $metadata = $this->reader->read(Closure::fromCallable([new SampleService(), 'run']));

        self::assertNotNull($metadata);
        self::assertSame('service.run', $metadata->name);
    }

    public function testCallableWithoutAttributeResolvesToNull(): void
    {
        self::assertNull($this->reader->read([new SampleService(), 'plain']));
        self::assertNull($this->reader->read(static fn (): int => 1));
    }

    public function testMetadataIsCachedPerCallable(): void
    {
        $first = $this->reader->read([new SampleService(), 'run']);
        $second = $this->reader->read([new SampleService(), 'run']);

        self::assertNotNull($first);
        self::assertSame($first, $second);
    }

    public function testFreedClosureDoesNotLeakMetadataToFreshClosure(): void
    {
        $attributed = new SampleService();

        $a = Closure::fromCallable([$attributed, 'run']);
        self::assertNotNull($this->reader->read($a));

        unset($a);
        gc_collect_cycles();

        $b = static fn (): int => 1;
        self::assertNull($this->reader->read($b), 'A fresh unattributed closure must not inherit freed closure A metadata.');
    }

    public function testObjectCacheDoesNotGrowUnboundedAcrossManyCallables(): void
    {
        for ($i = 0; $i < 2000; $i++) {
            $closure = static fn (): int => $i;
            $this->reader->read($closure);
        }

        gc_collect_cycles();

        $fresh = static fn (): int => 1;
        self::assertNull($this->reader->read($fresh), 'Entries for GC-eligible closures must not persist and leak.');
    }

    public function testAttributeCanBeConstructedDirectly(): void
    {
        $attribute = new ExecutedAs('explicit.name', 'task');

        self::assertSame('explicit.name', $attribute->name);
        self::assertSame('task', $attribute->kind);
    }
}

#[ExecutedAs('service.class', 'batch')]
final class AttributedService
{
    public function handle(): void
    {
    }
}

final class SampleService
{
    #[ExecutedAs('service.run', 'operation')]
    public function run(): void
    {
    }

    public function plain(): void
    {
    }
}

#[ExecutedAs('free.fn', 'task')]
function sample_free_function(): void
{
}
