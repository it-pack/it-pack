<?php

declare(strict_types=1);

namespace Tools\Dogfood\Run;

use Closure;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Measurement\MeasurementUnit;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Snapshot\SnapshotClassification;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use ItPack\RunIt\SDK\RunItInterface;
use Throwable;
use Tools\Dogfood\Exception\ToolFailedException;
use Tools\Dogfood\Process\ProcessResult;
use Tools\Dogfood\Process\ProcessRunnerInterface;
use Tools\Dogfood\Report\DogfoodReport;
use Tools\Dogfood\Report\ToolOutcome;
use Tools\Dogfood\Tool\DevTool;

/**
 * Represents each RunIt dev-tool run as a RunIt execution.
 *
 * For every {@see DevTool} the runner opens an execution through the SDK, runs the
 * tool as a subprocess, and records the outcome as measurements, an occurrence, and
 * a terminal snapshot. The publisher is drained afterwards so buffered artifacts
 * (and any flush observer) are flushed without re-entering the pipeline.
 *
 * Dogfooding never observes its own publication: the publisher is a leaf collector
 * (optionally buffered) whose flush observer keeps counters only, so the
 * {@see \ItPack\RunIt\Application\Publication\PublicationGuard} is never triggered.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class DogfoodRunner
{
    /**
     * @param Closure(string): void|null $emit status-line sink (defaults to STDERR)
     */
    public function __construct(
        private RunItInterface             $runIt,
        private ProcessRunnerInterface     $processRunner,
        private ArtifactPublisherInterface $publisher,
        private string                     $workingDirectory,
        private ?Closure                   $emit = null,
    ) {
    }

    public function runTool(DevTool $tool): ToolOutcome
    {
        $result = null;

        try {
            $this->runIt->run(
                ExecutionDefinition::create(
                    ExecutionName::fromString($tool->name),
                    ExecutionKind::fromString($tool->kind),
                ),
                function (ExecutionScopeInterface $scope) use ($tool, &$result): void {
                    $startedAt = hrtime(true);
                    $process = $this->processRunner->run($tool->command, $this->workingDirectory);
                    $durationMs = max(0, (int) ((hrtime(true) - $startedAt) / 1_000_000));
                    $result = $process;

                    $scope->measurements()->duration(
                        MeasurementName::fromString('runit.dogfood.tool.duration'),
                        $durationMs,
                        MeasurementUnit::Milliseconds,
                    );
                    $scope->measurements()->counter(
                        MeasurementName::fromString('runit.dogfood.tool.exit_code'),
                        $process->exitCode,
                        MeasurementUnit::Count,
                    );
                    $scope->measurements()->size(
                        MeasurementName::fromString('runit.dogfood.tool.stdout_bytes'),
                        strlen($process->stdout),
                        MeasurementUnit::Bytes,
                    );
                    $scope->measurements()->size(
                        MeasurementName::fromString('runit.dogfood.tool.stderr_bytes'),
                        strlen(substr($process->stderr, 0, 2000)),
                        MeasurementUnit::Bytes,
                    );

                    $summary = [
                        'tool' => $tool->name,
                        'command' => $tool->command,
                        'exit_code' => $process->exitCode,
                        'duration_ms' => round($durationMs, 3),
                        'stdout_bytes' => strlen($process->stdout),
                        'stderr_tail' => substr($process->stderr, 0, 2000),
                    ];

                    if ($process->exitCode !== 0) {
                        $scope->observations()->record(
                            sprintf('dogfood tool %s exited with code %d', $tool->name, $process->exitCode),
                            ObservationSeverity::Warning,
                            diagnosticCode: 'runit.dogfood.tool.nonzero_exit',
                        );
                    }

                    $occType = $process->exitCode === 0
                        ? OccurrenceType::fromString($tool->occurrenceType . '.completed.v1')
                        : OccurrenceType::fromString($tool->occurrenceType . '.failed.v1');

                    $scope->occurrences()->record($occType, 'runit.dogfood', data: $summary);
                    $scope->snapshots()->record(
                        SnapshotType::fromString($tool->snapshotType),
                        payload: $summary,
                        classification: SnapshotClassification::Terminal,
                    );

                    if ($process->exitCode !== 0) {
                        throw new ToolFailedException($tool->name, $process->exitCode);
                    }
                },
            );
        } catch (ToolFailedException) {
            // The failure was already recorded as an occurrence and snapshot above.
        } catch (Throwable $e) {
            $result ??= new ProcessResult($tool->command, 1, '', (string) $e, 0);
        }

        $this->drainPublisher();

        if ($result === null) {
            $result = new ProcessResult($tool->command, 1, '', 'Dogfood execution produced no result.', 0);
        }

        $this->line(sprintf('[dogfood] %s -> exit=%d', $tool->name, $result->exitCode));

        return new ToolOutcome($tool, $result);
    }

    /**
     * @param iterable<DevTool> $tools
     */
    public function runAll(iterable $tools): DogfoodReport
    {
        $outcomes = [];

        foreach ($tools as $tool) {
            $outcomes[] = $this->runTool($tool);
        }

        return new DogfoodReport($outcomes);
    }

    private function drainPublisher(): void
    {
        if ($this->publisher instanceof FlushablePublisherInterface) {
            $this->publisher->flush();
        }

        if ($this->publisher instanceof CloseablePublisherInterface) {
            $this->publisher->close();
        }
    }

    private function line(string $message): void
    {
        if ($this->emit !== null) {
            ($this->emit)($message);

            return;
        }

        fwrite(STDERR, $message . PHP_EOL);
    }
}
