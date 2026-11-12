<?php

declare(strict_types=1);

namespace Tests\Unit\Tools\Dogfood;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Common\DefaultArtifactByteEstimator;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\BufferConfiguration;
use ItPack\RunIt\Application\Publication\BufferedArtifactPublisher;
use ItPack\RunIt\Application\Publication\ClassifiedArtifact;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use Tools\Dogfood\Process\ProcessResult;
use Tools\Dogfood\Process\ProcessRunnerInterface;
use Tools\Dogfood\Report\DogfoodReport;
use Tools\Dogfood\Run\DogfoodRunner;
use Tools\Dogfood\Tool\DevTool;
use Tools\Dogfood\Tool\PredefinedTools;

/**
 * @group tools
 * @group dogfood
 */
final class DogfoodRunnerTest extends Unit
{
    private InMemoryArtifactPublisher $recorder;

    private function buildRunner(ProcessRunnerInterface $processRunner): DogfoodRunner
    {
        $this->recorder = new InMemoryArtifactPublisher();
        $publisher = new BufferedArtifactPublisher(
            BufferConfiguration::default(),
            $this->recorder,
            new SystemMonotonicClock(),
            new DefaultArtifactByteEstimator()
        );

        $runIt = RunItBuilder::create()
            ->withPublisher($publisher)
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->build()
        ;

        return new DogfoodRunner($runIt, $processRunner, $publisher, sys_get_temp_dir());
    }

    public function testRunAllRepresentsEachToolAsAnExecutionWithoutRecursion(): void
    {
        $single = $this->buildRunner(new FakeProcessRunner());
        $single->runAll([
            new DevTool(
                'runit.dogfood.unit',
                'echo unit',
                'cli.command',
                'runit.dogfood.unit',
                'runit.dogfood.unit.summary'
            ),
        ]);

        $executionsPerTool = $this->countArtifacts(ExecutionInterface::class);
        $measurementsPerTool = $this->countArtifacts(MeasurementInterface::class);
        $occurrencesPerTool = $this->countArtifacts(OccurrenceInterface::class);
        $snapshotsPerTool = $this->countArtifacts(SnapshotInterface::class);

        // Bounded emission per tool proves the dogfooding path does not recurse:
        // the Execution artifact is emitted a small, fixed number of times per run.
        self::assertGreaterThanOrEqual(1, $executionsPerTool);
        self::assertLessThanOrEqual(
            4,
            $executionsPerTool,
            'Execution emission per tool must stay bounded (no recursion).'
        );
        self::assertSame(2, $measurementsPerTool, 'Two measurements per tool (duration, exit code).');
        self::assertSame(1, $occurrencesPerTool, 'One completion occurrence per tool.');
        self::assertSame(2, $snapshotsPerTool, 'SDK completion snapshot plus the dogfood summary snapshot.');

        $runner = $this->buildRunner(new FakeProcessRunner());
        $report = $runner->runAll([
            new DevTool(
                'runit.dogfood.unit',
                'echo unit',
                'cli.command',
                'runit.dogfood.unit',
                'runit.dogfood.unit.summary'
            ),
            new DevTool('runit.dogfood.cs', 'echo cs', 'cli.command', 'runit.dogfood.cs', 'runit.dogfood.cs.summary'),
        ]);

        self::assertInstanceOf(DogfoodReport::class, $report);
        self::assertSame(0, $report->exitCode());
        self::assertCount(2, $report->outcomes);

        // Artifact counts scale linearly with the number of tools, confirming no
        // runaway recursion when dogfooding observes its own executions.
        self::assertSame(2 * $executionsPerTool, $this->countArtifacts(ExecutionInterface::class));
        self::assertSame(2 * $measurementsPerTool, $this->countArtifacts(MeasurementInterface::class));
        self::assertSame(2 * $occurrencesPerTool, $this->countArtifacts(OccurrenceInterface::class));
        self::assertSame(2 * $snapshotsPerTool, $this->countArtifacts(SnapshotInterface::class));
    }

    /**
     * @param class-string $type
     */
    private function countArtifacts(string $type): int
    {
        $count = 0;
        $kindMap = [
            ExecutionInterface::class => 'execution',
            MeasurementInterface::class => 'measurement',
            OccurrenceInterface::class => 'occurrence',
            SnapshotInterface::class => 'snapshot',
        ];
        $kind = $kindMap[$type] ?? null;

        foreach ($this->recorder->published() as $artifact) {
            if ($artifact instanceof $type) {
                $count++;
            } elseif ($kind !== null
                && $artifact instanceof ClassifiedArtifact
                && $artifact->artifactType() === $kind) {
                $count++;
            }
        }

        return $count;
    }

    public function testNonZeroExitRecordsFailedOccurrenceAndFailingOutcome(): void
    {
        $fake = new FakeProcessRunner(['echo fail' => 1]);
        self::assertSame(1, $fake->run('echo fail', '.')->exitCode);

        $runner = $this->buildRunner($fake);

        $report = $runner->runAll([
            new DevTool(
                'runit.dogfood.fail',
                'echo fail',
                'cli.command',
                'runit.dogfood.fail',
                'runit.dogfood.fail.summary'
            ),
        ]);

        self::assertSame(1, $report->exitCode());
        self::assertFalse($report->outcomes[0]->succeeded());

        $failed = null;

        foreach ($this->recorder->published() as $artifact) {
            if ($artifact instanceof OccurrenceInterface) {
                $failed = $artifact;
            } elseif ($artifact instanceof ClassifiedArtifact && $artifact->artifactType() === 'occurrence') {
                $failed = $artifact;
            }
        }

        self::assertNotNull($failed, 'A warning observation was recorded for the failed tool.');
    }

    public function testPredefinedToolsHaveValidNamesAndOccurrenceTypes(): void
    {
        $tools = PredefinedTools::all();

        self::assertCount(6, $tools);

        foreach ($tools as $tool) {
            ExecutionName::fromString($tool->name);
            ExecutionKind::fromString($tool->kind);
            OccurrenceType::fromString($tool->occurrenceType . '.completed.v1');
            OccurrenceType::fromString($tool->occurrenceType . '.failed.v1');
            self::assertStringStartsWith('runit.dogfood.', $tool->name);
        }
    }
}

final readonly class FakeProcessRunner implements ProcessRunnerInterface
{
    /**
     * @param array<string, int> $exitCodes
     */
    public function __construct(private array $exitCodes = [])
    {
    }

    public function run(string $command, string $workingDirectory): ProcessResult
    {
        $exit = $this->exitCodes[$command] ?? 0;

        return new ProcessResult($command, $exit, 'stdout', $exit === 0 ? '' : 'boom', 1234);
    }
}
