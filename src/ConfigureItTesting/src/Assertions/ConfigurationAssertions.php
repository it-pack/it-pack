<?php

declare(strict_types=1);

namespace ItPack\ConfigureItTesting\Assertions;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\ValueObject\Format;
use Nette\Schema\Processor;
use Nette\Schema\Schema;
use Nette\Schema\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit assertions for resolved {@see ConfigurationGraphInterface} instances.
 *
 * Use it in a test case:
 *
 *   use ItPack\ConfigureItTesting\Assertions\ConfigurationAssertions;
 *   class MyTest extends TestCase { use ConfigurationAssertions; }
 *
 * Every method reads provenance via the node's {@see \ItPack\ConfigureIt\Contract\ValueObject\ProvenanceInterface}
 * and fails with a descriptive message on mismatch. `assertConfigValid` runs a
 * `Nette\Schema\Expect` definition through Nette's processor (the same engine
 * ConfigureIt wraps) so consumers can prove their schema accepts sample input.
 *
 * @mixin TestCase
 */
trait ConfigurationAssertions
{
    public function assertGraphHasKey(ConfigurationGraphInterface $graph, string $key, string $message = ''): void
    {
        $this->assertTrue(
            $graph->has($key),
            '' !== $message ? $message : sprintf('Expected the graph to contain key "%s".', $key),
        );
    }

    public function assertGraphNotHasKey(ConfigurationGraphInterface $graph, string $key, string $message = ''): void
    {
        $this->assertFalse(
            $graph->has($key),
            '' !== $message ? $message : sprintf('Expected the graph NOT to contain key "%s".', $key),
        );
    }

    /**
     * @param array<string, mixed> $expected
     */
    public function assertGraphEquals(ConfigurationGraphInterface $graph, array $expected, string $message = ''): void
    {
        $this->assertSame(
            $expected,
            $graph->toArray(),
            '' !== $message ? $message : 'The resolved graph does not match the expected tree.',
        );
    }

    public function assertKeyEquals(ConfigurationGraphInterface $graph, string $key, mixed $expected, string $message = ''): void
    {
        $this->assertGraphHasKey($graph, $key, $message);

        $this->assertSame(
            $expected,
            $graph->node($key)->value(),
            '' !== $message ? $message : sprintf('Resolved value at "%s" does not match.', $key),
        );
    }

    public function assertKeyResolvedFrom(ConfigurationGraphInterface $graph, string $key, string $sourceKind, string $message = ''): void
    {
        $this->assertGraphHasKey($graph, $key, $message);

        $this->assertSame(
            $sourceKind,
            $graph->node($key)->provenance()->sourceKind(),
            '' !== $message ? $message : sprintf('Key "%s" was expected to resolve from source kind "%s".', $key, $sourceKind),
        );
    }

    public function assertKeyOverriddenBy(ConfigurationGraphInterface $graph, string $key, string $overriddenSource, string $message = ''): void
    {
        $this->assertGraphHasKey($graph, $key, $message);

        $provenance = $graph->node($key)->provenance();
        $this->assertTrue(
            $provenance->overridden(),
            '' !== $message ? $message : sprintf('Key "%s" was expected to be overridden.', $key),
        );
        $this->assertSame(
            $overriddenSource,
            $provenance->overriddenSource(),
            '' !== $message ? $message : sprintf('Key "%s" was expected to override source "%s".', $key, $overriddenSource),
        );
    }

    public function assertKeyIsSensitive(ConfigurationGraphInterface $graph, string $key, bool $expected = true, string $message = ''): void
    {
        $this->assertGraphHasKey($graph, $key, $message);

        $this->assertSame(
            $expected,
            $graph->node($key)->provenance()->isSensitive(),
            '' !== $message ? $message : sprintf('Sensitivity of key "%s" did not match.', $key),
        );
    }

    public function assertKeyFormat(ConfigurationGraphInterface $graph, string $key, Format $format, string $message = ''): void
    {
        $this->assertGraphHasKey($graph, $key, $message);

        $this->assertSame(
            $format,
            $graph->node($key)->provenance()->format(),
            '' !== $message ? $message : sprintf('Format of key "%s" did not match.', $key),
        );
    }

    public function assertKeyAdapter(ConfigurationGraphInterface $graph, string $key, string $adapter, string $message = ''): void
    {
        $this->assertGraphHasKey($graph, $key, $message);

        $this->assertSame(
            $adapter,
            $graph->node($key)->provenance()->adapter(),
            '' !== $message ? $message : sprintf('Adapter of key "%s" did not match.', $key),
        );
    }

    public function assertKeyPriority(ConfigurationGraphInterface $graph, string $key, int $priority, string $message = ''): void
    {
        $this->assertGraphHasKey($graph, $key, $message);

        $this->assertSame(
            $priority,
            $graph->node($key)->provenance()->priority(),
            '' !== $message ? $message : sprintf('Priority of key "%s" did not match.', $key),
        );
    }

    public function assertKeyHasProcessor(ConfigurationGraphInterface $graph, string $key, string $stage, string $message = ''): void
    {
        $this->assertGraphHasKey($graph, $key, $message);

        $this->assertContains(
            $stage,
            $graph->node($key)->provenance()->processorTrail(),
            '' !== $message ? $message : sprintf('Key "%s" was expected to have been touched by processor "%s".', $key, $stage),
        );
    }

    /**
     * Assert that `$data` validates against a `Nette\Schema\Expect` definition.
     *
     * @param array<string, mixed> $data
     */
    public function assertConfigValid(Schema $schema, array $data, string $message = ''): void
    {
        $processor = new Processor();

        try {
            $processor->process($schema, $data);
        } catch (ValidationException $exception) {
            $this->fail('' !== $message ? $message : 'Configuration is invalid: '.$exception->getMessage());
        }

        $this->addToAssertionCount(1);
    }
}
