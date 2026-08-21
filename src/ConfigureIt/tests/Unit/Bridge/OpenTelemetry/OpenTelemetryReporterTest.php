<?php

declare(strict_types=1);

namespace Tests\Unit\Bridge\OpenTelemetry;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Adapter\JsonLoader;
use ItPack\ConfigureIt\Adapter\NativeConfigurationSchema;
use ItPack\ConfigureIt\Adapter\PhpLoader;
use ItPack\ConfigureIt\Bridge\OpenTelemetry\InMemoryTelemetrySink;
use ItPack\ConfigureIt\Bridge\OpenTelemetry\SemanticConventions;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\Execution\ExecutionContext;
use ItPack\ConfigureIt\Execution\ResolutionReporter;
use Nette\Schema\Expect;

class OpenTelemetryReporterTest extends Unit
{
    private string $dir;

    private InMemoryTelemetrySink $sink;

    protected function _before(): void
    {
        $this->dir = sys_get_temp_dir() . '/cfg-otel-' . uniqid();
        mkdir($this->dir, 0o777, true);
        file_put_contents($this->dir . '/base.php', '<?php return ["db" => ["host" => "base-host"], "app" => ["name" => "base"]];');
        file_put_contents($this->dir . '/local.json', '{"db":{"host":"local-host"},"app":{"debug":true}}');

        $this->sink = new InMemoryTelemetrySink();
    }

    protected function _after(): void
    {
        foreach (array_diff(scandir($this->dir), ['.', '..']) as $item) {
            unlink($this->dir . '/' . $item);
        }

        rmdir($this->dir);
    }

    private function buildResolver(?ExecutionContext $context = null): ConfigurationBuilder
    {
        $builder = new ConfigurationBuilder()
            ->addReader(new FileReader())
            ->addSource(new FileSource($this->dir))
            ->addLoader(new PhpLoader())
            ->addLoader(new JsonLoader())
            ->withTelemetry($this->sink)
        ;

        if ($context instanceof ExecutionContext) {
            $builder->withExecutionContext($context);
        }

        return $builder;
    }

    /**
     * @param array<int, array{name: string, ...}> $spans
     * @return array<int, string>
     */
    private function spanNames(array $spans): array
    {
        return array_map(static fn (array $s): string => $s['name'], $spans);
    }

    /**
     * @param array<int, array{name: string, ...}> $events
     * @return array<int, string>
     */
    private function eventNames(array $events): array
    {
        return array_map(static fn (array $e): string => $e['name'], $events);
    }

    public function testEmitsTraceSpansEventsAndMetricsForAResolution(): void
    {
        $reporter = new ResolutionReporter();
        $resolver = $this->buildResolver()->build($reporter);

        $snapshot = $resolver->resolve();

        verify($snapshot->get('db.host'))->equals('local-host');

        // The inner reporter still records the raw Execution Occurrences.
        verify($reporter->occurrences())->notEmpty();

        // One trace started and ended.
        verify(count($this->sink->traces()))->equals(2);
        verify($this->sink->traces()[0]['name'])->equals(SemanticConventions::SPAN_RESOLVE);

        $spans = $this->spanNames($this->sink->spans());
        verify(in_array(SemanticConventions::SPAN_LOAD, $spans, true))->true();
        verify(in_array(SemanticConventions::SPAN_RESOLVE_STAGES, $spans, true))->true();
        verify(in_array(SemanticConventions::SPAN_SNAPSHOT, $spans, true))->true();

        $events = $this->eventNames($this->sink->events());
        verify(in_array(SemanticConventions::EVENT_LOADED, $events, true))->true();
        verify(in_array(SemanticConventions::EVENT_RESOLVED, $events, true))->true();
        verify(in_array(SemanticConventions::EVENT_SNAPSHOT_CREATED, $events, true))->true();

        // Metrics: occurrences counter + node/override/unresolved measures.
        $counterNames = array_map(static fn (array $c): string => $c['name'], $this->sink->counters());
        verify(in_array(SemanticConventions::METRIC_OCCURRENCES, $counterNames, true))->true();

        $measureNames = array_map(static fn (array $m): string => $m['name'], $this->sink->measures());
        verify(in_array(SemanticConventions::METRIC_NODES, $measureNames, true))->true();
        verify(in_array(SemanticConventions::METRIC_RESOLUTION_DURATION, $measureNames, true))->true();
    }

    public function testCapturesValidationSpanAndEventWhenSchemaIsPresent(): void
    {
        $schema = new NativeConfigurationSchema(Expect::structure([
            'db' => Expect::structure(['host' => Expect::string()]),
        ])->otherItems(Expect::mixed())->castTo('array'));

        $resolver = $this->buildResolver()->withSchema($schema)->build();

        $resolver->resolve();

        $spans = $this->spanNames($this->sink->spans());
        verify(in_array(SemanticConventions::SPAN_VALIDATE, $spans, true))->true();

        $events = $this->eventNames($this->sink->events());
        verify(in_array(SemanticConventions::EVENT_VALIDATED, $events, true))->true();
    }

    public function testAttachesExecutionContextToTrace(): void
    {
        $context = new ExecutionContext('staging', ['tenant' => 'acme']);

        $resolver = $this->buildResolver($context)->build();
        $resolver->resolve();

        verify($this->sink->traces()[0]['attributes'][SemanticConventions::ATTR_ENVIRONMENT] ?? null)
            ->equals('staging')
        ;
        verify($this->sink->traces()[0]['attributes']['config.context.tenant'] ?? null)
            ->equals('acme')
        ;
    }

    public function testEmitsObservationEventsForDiagnostics(): void
    {
        // Force an unresolved placeholder so the diagnostics observer fires.
        file_put_contents($this->dir . '/local.json', '{"app":{"endpoint":"${MISSING}"}}');

        $resolver = $this->buildResolver()->build();
        $resolver->resolve();

        $events = $this->eventNames($this->sink->events());
        verify(in_array(SemanticConventions::EVENT_OBSERVATION, $events, true))->true();

        $observationEvents = array_filter(
            $this->sink->events(),
            static fn (array $e): bool => $e['name'] === SemanticConventions::EVENT_OBSERVATION,
        );
        $sample = array_values($observationEvents)[0];
        verify($sample['attributes'][SemanticConventions::ATTR_LEVEL] ?? null)->equals('warning');
    }
}
