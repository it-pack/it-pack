<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Factory;

use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Contract\Application\ExecutionSnapshotFactoryInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionSnapshotInterface;
use ItPack\RunIt\Contract\Domain\Factory\StringIdentifierFactoryInterface;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;
use ItPack\RunIt\Contract\Domain\SnapshotIdInterface;
use ItPack\RunIt\Contract\Domain\SnapshotTypeInterface;
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;
use ItPack\RunIt\Domain\Snapshot\ExecutionSnapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotClassification;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;

/**
 * Default producer of the terminal execution snapshot.
 *
 * It captures the normalized execution state into an immutable, hash-bound `terminal`
 * snapshot. Identity and hashing are supplied at construction so the factory stays
 * free of randomness and extension-specific hashing.
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
final class ExecutionSnapshotFactory implements ExecutionSnapshotFactoryInterface
{
    /**
     * @param StringIdentifierFactoryInterface<SnapshotIdInterface> $snapshotIdFactory
     */
    public function __construct(
        private readonly StringIdentifierFactoryInterface $snapshotIdFactory,
        private readonly HashCalculatorInterface          $hashCalculator,
        private ?SnapshotTypeInterface                    $type = null,
        private readonly ?ProvenanceAuthority                      $authority = null,
    ) {
    }

    /**
     * @throws InvalidValueExceptionInterface
     */
    public function terminal(ExecutionInterface $execution): ExecutionSnapshotInterface
    {
        $id = $this->snapshotIdFactory->build();
        $this->type ??= SnapshotType::executionTerminal();

        $snapshot = ExecutionSnapshot::create(
            $id,
            $execution->executionId(),
            $this->type,
            ExecutionSnapshot::SCHEMA_VERSION,
            $execution->normalize(),
            $this->hashCalculator,
            classification: SnapshotClassification::Terminal,
        );

        // Issue an SDK-controlled export ID for the terminal snapshot and register
        // the execution→snapshot edge so provenance can be verified at publication.
        // Only when an authority is supplied (the SDK shares its own); custom
        // factories constructed without one never accumulate unreleased records.
        if ($this->authority instanceof ProvenanceAuthority) {
            $exportId = $this->authority->bindArtifact(
                'snapshot',
                'terminal-snapshot',
                $execution->executionId()->toString(),
                $id->toString(),
                'execution-terminal-snapshot',
            );
            $this->authority->attest($snapshot, $exportId);
        }

        return $snapshot;
    }
}
