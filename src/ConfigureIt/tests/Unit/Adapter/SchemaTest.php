<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Closure;
use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\Schema;
use ItPack\ConfigureIt\Contract\ContextInterface;
use ItPack\ConfigureIt\Contract\MessageInterface;
use Nette\Schema\Context as NetteContext;
use Nette\Schema\Expect;
use Nette\Schema\Message as NetteMessage;

/**
 * The Nette Schema port adapter (ADR-0002). Every call must unwrap the
 * ConfigureIt context to its vendor adaptee and delegate verbatim — success,
 * error and warning paths alike — without leaking Nette types into the Core.
 *
 * @covers \ItPack\ConfigureIt\Adapter\Schema
 */
final class SchemaTest extends Unit
{
    public function testNormalizeDelegatesToTheWrappedSchema(): void
    {
        $normalized = $this->schema()->normalize(['port' => 8080], $this->context(new NetteContext()));

        verify($normalized)->equals(['port' => 8080]);
    }

    public function testCompleteAppliesDeclaredDefaults(): void
    {
        $completed = $this->schema()->complete(['port' => 8080], $this->context(new NetteContext()));

        verify($completed)->equals(['port' => 8080, 'name' => 'api']);
    }

    public function testCompleteDefaultBuildsTheDefaultShape(): void
    {
        $defaults = $this->schema()->completeDefault($this->context(new NetteContext()));

        verify($defaults)->equals(['port' => null, 'name' => 'api']);
    }

    public function testMergeLetsTheValueWinOverTheBase(): void
    {
        $merged = $this->schema()->merge(['port' => 9090], ['port' => 8080, 'name' => 'base']);

        verify($merged)->equals(['port' => 9090, 'name' => 'base']);
    }

    public function testMergeWithoutABaseReturnsTheValue(): void
    {
        verify($this->schema()->merge(['port' => 9090], null))->equals(['port' => 9090]);
    }

    public function testCompleteRecordsErrorsOnTheSharedContext(): void
    {
        $netteContext = new NetteContext();

        $result = $this->schema()->complete(['port' => 'not-an-int'], $this->context($netteContext));

        verify($result)->null();
        verify(count($netteContext->errors))->equals(1);
        verify(str_contains($netteContext->errors[0]->toString(), 'expects to be int'))->true();
    }

    public function testCompleteRecordsWarningsForDeprecatedItems(): void
    {
        $schema = new Schema(
            Expect::structure([
                'old' => Expect::int()->deprecated('The %path% is gone.'),
            ])->castTo('array'),
        );

        $netteContext = new NetteContext();
        $context = $this->context($netteContext);

        $schema->complete($schema->normalize(['old' => 1], $context), $context);

        verify(count($netteContext->errors))->equals(0);
        verify(count($netteContext->warnings))->equals(1);
        verify($netteContext->warnings[0]->toString())->equals("The 'old' is gone.");
    }

    public function testCompleteHonoursSkipDefaultsFromTheContext(): void
    {
        $netteContext = new NetteContext();
        $netteContext->skipDefaults = true;
        $context = $this->context($netteContext);

        $schema = $this->schema();
        $completed = $schema->complete($schema->normalize(['port' => 8080], $context), $context);

        verify($completed)->equals(['port' => 8080]);
    }

    public function testGetAdapteeReturnsTheInjectedNetteSchema(): void
    {
        $nette = Expect::int();
        $schema = new Schema($nette);

        self::assertSame($nette, $schema->getAdaptee());
        self::assertSame($nette, $schema->adaptee);
    }

    public function testScalarSchemasDelegateJustAsStructuresDo(): void
    {
        $schema = new Schema(Expect::string('fallback'));
        $context = $this->context(new NetteContext());

        verify($schema->normalize('value', $context))->equals('value');
        verify($schema->complete('value', $context))->equals('value');
        verify($schema->completeDefault($context))->equals('fallback');
    }
    /**
     * A minimal ContextInterface fake backed by a real Nette context, so the
     * adapter's unwrapping is exercised without pulling in the Core context.
     */
    private function context(NetteContext $adaptee): ContextInterface
    {
        return new class ($adaptee) implements ContextInterface {
            public function __construct(private NetteContext $adaptee)
            {
            }

            public function addError(string $message, string $code, array $variables = []): MessageInterface
            {
                return $this->wrap($this->adaptee->addError($message, $code, $variables));
            }

            public function addWarning(string $message, string $code, array $variables = []): MessageInterface
            {
                return $this->wrap($this->adaptee->addWarning($message, $code, $variables));
            }

            public function createChecker(): Closure
            {
                return $this->adaptee->createChecker();
            }

            public function getAdaptee(): object
            {
                return $this->adaptee;
            }

            private function wrap(NetteMessage $message): MessageInterface
            {
                return new class ($message) implements MessageInterface {
                    public function __construct(private NetteMessage $message)
                    {
                    }

                    public function toString(): string
                    {
                        return $this->message->toString();
                    }

                    public function __toString(): string
                    {
                        return $this->toString();
                    }
                };
            }
        };
    }

    private function schema(): Schema
    {
        return new Schema(
            Expect::structure([
                'port' => Expect::int(),
                'name' => Expect::string('api'),
            ])->castTo('array'),
        );
    }
}
