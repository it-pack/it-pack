<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter\Reporting;

use ItPack\DisenchantIt\Contract\Reporting\ExecutionRecorderInterface;
use ItPack\DisenchantIt\Contract\Reporting\JsonReportRecorderInterface;
use ItPack\DisenchantIt\Factory\DisenchanterFactory;
use ItPack\DisenchantIt\ValueObject\Execution\ExecutionArtifactInterface;

/**
 * ExecutionRecorderInterface implementation that accumulates every recorded
 * artifact and renders them as a JSON report. Unlike NullExecutionRecorder it
 * keeps state, so it is intentionally NOT readonly (it must collect a run's
 * artifacts in memory before toJson() is called). Wire it into
 * DisenchanterFactory::create(recorder: $this) to obtain a machine-readable run
 * report without changing any Core behavior.
 */
final class JsonReportRecorder implements JsonReportRecorderInterface
{
    /** @var list<ExecutionArtifactInterface> */
    private array $artifacts = [];

    public function record(ExecutionArtifactInterface $artifact): void
    {
        $this->artifacts[] = $artifact;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reports(): array
    {
        return array_map(static fn (ExecutionArtifactInterface $a): array => $a->normalize(), $this->artifacts);
    }

    public function toJson(): string
    {
        $json = json_encode($this->reports(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode the run report as JSON.');
        }

        return $json;
    }
}
