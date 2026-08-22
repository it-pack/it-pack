<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter;

use ItPack\DisenchantIt\Contract\SarifRecorderInterface;
use ItPack\DisenchantIt\ValueObject\Execution\ExecutionArtifactInterface;

/**
 * ExecutionRecorderInterface implementation that accumulates every recorded
 * artifact and renders them as a SARIF 2.1.0 log. Like JsonReportRecorder it
 * keeps mutable state (NOT readonly) so it can collect a run's artifacts before
 * toSarif() is called.
 *
 * Each occurrence becomes one SARIF `result` (ruleId = the occurrence name,
 * message = the JSON-encoded payload). Deterministic and bounded — no arbitrary
 * PHP objects are serialized.
 */
final class SarifRecorder implements SarifRecorderInterface
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

    public function toSarif(): string
    {
        $results = [];
        foreach ($this->artifacts as $artifact) {
            $normalized = $artifact->normalize();
            $results[] = [
                'ruleId' => $normalized['kind'],
                'level' => 'note',
                'message' => [
                    'text' => json_encode($normalized['payload'] ?? [], JSON_UNESCAPED_SLASHES),
                ],
            ];
        }

        $doc = [
            '$schema' => 'https://json.schemastore.org/sarif-2.1.0.json',
            'version' => '2.1.0',
            'runs' => [
                [
                    'tool' => [
                        'driver' => [
                            'name' => 'DisenchantIt',
                            'informationUri' => 'https://github.com/it-pack/disenchant-it',
                        ],
                    ],
                    'results' => $results,
                ],
            ],
        ];

        $sarif = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($sarif === false) {
            throw new \RuntimeException('Failed to encode the SARIF report.');
        }

        return $sarif;
    }
}
