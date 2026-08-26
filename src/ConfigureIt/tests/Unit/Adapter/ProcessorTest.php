<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\Processor;
use ItPack\ConfigureIt\Contract\ContextInterface;
use ItPack\ConfigureIt\Contract\SchemaInterface;
use Nette\Schema\Expect;
use Nette\Schema\Processor as NetteProcessor;
use Nette\Schema\Schema as NetteSchema;
use Nette\Schema\ValidationException as NetteValidationException;

/**
 * The Nette Schema processor port adapter. Unwraps the schema port to its vendor
 * adaptee and delegates processing, multi-dataset merging, default skipping and
 * deprecation warnings.
 *
 * Translation of the vendor exception into the domain ValidationResult is the
 * responsibility of NativeConfigurationSchema, not of this adapter — so the raw
 * vendor exception surfacing here is the documented contract.
 *
 * @covers \ItPack\ConfigureIt\Adapter\Processor
 */
final class ProcessorTest extends Unit
{
    /**
     * A schema port fake that carries a real Nette schema as its adaptee.
     */
    private function schema(NetteSchema $adaptee): SchemaInterface
    {
        return new class ($adaptee) implements SchemaInterface {
            public function __construct(private NetteSchema $adaptee)
            {
            }

            public function normalize(mixed $value, ContextInterface $context): mixed
            {
                throw new \LogicException('The processor adapter must not call normalize() directly.');
            }

            public function merge(mixed $value, mixed $base): mixed
            {
                throw new \LogicException('The processor adapter must not call merge() directly.');
            }

            public function complete(mixed $value, ContextInterface $context): mixed
            {
                throw new \LogicException('The processor adapter must not call complete() directly.');
            }

            public function completeDefault(ContextInterface $context): mixed
            {
                throw new \LogicException('The processor adapter must not call completeDefault() directly.');
            }

            public function getAdaptee(): object
            {
                return $this->adaptee;
            }
        };
    }

    private function appSchema(): SchemaInterface
    {
        return $this->schema(
            Expect::structure([
                'port' => Expect::int(),
                'name' => Expect::string('api'),
            ])->castTo('array'),
        );
    }

    public function testProcessCompletesValidData(): void
    {
        $result = new Processor()->process($this->appSchema(), ['port' => 8080]);

        verify($result)->equals(['port' => 8080, 'name' => 'api']);
    }

    public function testProcessSurfacesTheVendorValidationException(): void
    {
        $processor = new Processor();

        try {
            $processor->process($this->appSchema(), ['port' => 'not-an-int']);
            self::fail('Expected the invalid payload to be rejected.');
        } catch (NetteValidationException $e) {
            verify(str_contains($e->getMessage(), "The item 'port' expects to be int"))->true();
            verify(count($e->getMessageObjects()))->equals(1);
        }
    }

    public function testProcessMultipleMergesDatasetsInOrder(): void
    {
        $result = new Processor()->processMultiple($this->appSchema(), [
            ['port' => 8080, 'name' => 'base'],
            ['port' => 9090],
        ]);

        // The later dataset wins per key; untouched keys survive the merge.
        verify($result)->equals(['port' => 9090, 'name' => 'base']);
    }

    public function testProcessMultipleWithAnEmptyDatasetYieldsTheDefaults(): void
    {
        $result = new Processor()->processMultiple($this->appSchema(), []);

        verify($result)->equals(['port' => null, 'name' => 'api']);
    }

    public function testProcessMultipleSurfacesValidationErrors(): void
    {
        $this->expectException(NetteValidationException::class);

        new Processor()->processMultiple($this->appSchema(), [['port' => 1], ['port' => 'nope']]);
    }

    public function testSkipDefaultsOmitsDefaultedItems(): void
    {
        $processor = new Processor();
        $processor->skipDefaults();

        verify($processor->process($this->appSchema(), ['port' => 8080]))->equals(['port' => 8080]);
    }

    public function testSkipDefaultsCanBeTurnedBackOff(): void
    {
        $processor = new Processor();

        $processor->skipDefaults(true);
        verify($processor->process($this->appSchema(), ['port' => 8080]))->equals(['port' => 8080]);

        $processor->skipDefaults(false);
        verify($processor->process($this->appSchema(), ['port' => 8080]))
            ->equals(['port' => 8080, 'name' => 'api'])
        ;
    }

    public function testWarningsAreEmptyBeforeAnyProcessing(): void
    {
        verify(new Processor()->getWarnings())->equals([]);
    }

    public function testWarningsExposeDeprecations(): void
    {
        $schema = $this->schema(
            Expect::structure([
                'old' => Expect::int()->deprecated('The %path% is gone.'),
            ])->castTo('array'),
        );

        $processor = new Processor();
        $processor->process($schema, ['old' => 1]);

        verify($processor->getWarnings())->equals(["The 'old' is gone."]);
    }

    public function testWarningsAreScopedToTheLastRun(): void
    {
        $schema = $this->schema(
            Expect::structure([
                'old' => Expect::int()->deprecated('The %path% is gone.'),
            ])->castTo('array'),
        );

        $processor = new Processor();
        $processor->process($schema, ['old' => 1]);
        verify(count($processor->getWarnings()))->equals(1);

        // A fresh run starts from a fresh context, so stale warnings never leak.
        $processor->process($schema, []);
        verify($processor->getWarnings())->equals([]);
    }

    public function testGetAdapteeReturnsTheInjectedNetteProcessor(): void
    {
        $nette = new NetteProcessor();

        self::assertSame($nette, new Processor($nette)->getAdaptee());
    }

    public function testDefaultConstructorProvidesItsOwnNetteProcessor(): void
    {
        verify(new Processor()->getAdaptee() instanceof NetteProcessor)->true();
    }
}
