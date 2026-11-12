<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use RuntimeException;
use Throwable;

/**
 * Internal authority for issuance and verification of SDK-controlled export identities.
 *
 * Every locally created artifact receives an opaque export ID minted from controlled
 * randomness, bound to its kind, role, owning execution, and recorded relationships.
 * Business-facing IDs (the caller-supplied generator output) are preserved but are
 * never treated as provenance; only the export ID verified against this authority
 * counts as trust.
 *
 * The authority is scoped to the SDK instance. Records are retained through terminal
 * publication and execution-scope cleanup, then released. Queued safe projections
 * remain self-contained after release; a late raw artifact without a live binding
 * drops and reports.
 *
 * Concurrency: records are mutable only through the authority's own methods; each
 * record entry is never modified in place except for its released flag, which is set
 * once during release. The authority is safe for use from a single persistent worker.
 *
 * @internal provenance authority; not a public contract.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ProvenanceAuthority implements ProvenanceAuthorityInterface
{
    /**
     * Valid endpoint role pairs per relationship type. Only these combinations
     * are recorded; anything else is rejected as an invalid edge.
     *
     * @var array<string, array{from: list<string>, to: list<string>}>
     */
    private const array RELATIONSHIP_ROLES = [
        'parent' => [
            'from' => ['execution', 'activity'],
            'to' => ['execution'],
        ],
        'link' => [
            'from' => ['execution'],
            'to' => ['execution'],
        ],
        'execution-activity' => [
            'from' => ['execution'],
            'to' => ['activity'],
        ],
        'execution-observation' => [
            'from' => ['execution'],
            'to' => ['observation'],
        ],
        'execution-occurrence' => [
            'from' => ['execution'],
            'to' => ['occurrence'],
        ],
        'execution-measurement' => [
            'from' => ['execution'],
            'to' => ['measurement'],
        ],
        'execution-snapshot' => [
            'from' => ['execution'],
            'to' => ['snapshot'],
        ],
        'execution-terminal-snapshot' => [
            'from' => ['execution'],
            'to' => ['snapshot', 'terminal-snapshot'],
        ],
    ];

    /**
     * @var array<string, array{kind: string, role: string, executionId: string, businessId: string, relationships: list<string>, released: bool}>
     */
    private array $records = [];

    /**
     * @var array<string, list<string>> export IDs keyed by "businessId\0executionId"
     */
    private array $byBusinessExecution = [];

    /**
     * @var array<string, list<string>> export IDs keyed by executionId
     */
    private array $byExecution = [];

    private bool $releasing = false;

    public function __construct(private readonly ?InternalHealthReporterInterface $health = null)
    {
    }

    /**
     * Mint an opaque export ID and bind it to a locally created artifact.
     *
     * The export ID is independent of the caller-supplied business ID and carries
     * no meaning outside this authority. The binding records kind, role, owning
     * execution, and the artifact's own business ID so verification can reject
     * copied, substituted, or forged evidence.
     *
     * @param list<string> $relationships
     */
    public function issueExportId(
        string $kind,
        string $role,
        string $executionId,
        string $businessId,
        array $relationships = [],
    ): string {
        if ($this->releasing) {
            throw new RuntimeException('Provenance authority is releasing; no new issuance permitted.');
        }

        $exportId = 'runit-export-' . bin2hex(random_bytes(16));

        $this->records[$exportId] = [
            'kind' => $kind,
            'role' => $role,
            'executionId' => $executionId,
            'businessId' => $businessId,
            'relationships' => $relationships,
            'released' => false,
        ];

        $key = $businessId . "\0" . $executionId;
        $this->byBusinessExecution[$key][] = $exportId;
        $this->byExecution[$executionId][] = $exportId;

        return $exportId;
    }

    /**
     * Verify that an export ID is bound to the expected role and execution.
     *
     * Returns true only when the export ID was issued by this authority, has not
     * been released, and matches both the role and execution ID. String equality
     * alone is never accepted as proof.
     */
    public function verifyExportId(string $exportId, string $role, string $executionId): bool
    {
        $record = $this->records[$exportId] ?? null;

        if ($record === null || $record['released']) {
            return false;
        }

        return $record['role'] === $role && $record['executionId'] === $executionId;
    }

    /**
     * Record a verified relationship edge between two issued export IDs.
     *
     * Both endpoints must be issued and unreleased. Returns false if either
     * endpoint is unknown or released, so callers can treat the relationship
     * as unverified and drop-and-report.
     */
    public function registerRelationship(string $fromExportId, string $toExportId, string $relationshipType): bool
    {
        $from = $this->records[$fromExportId] ?? null;
        $to = $this->records[$toExportId] ?? null;

        if ($from === null || $from['released'] || $to === null || $to['released']) {
            return false;
        }

        $roles = self::RELATIONSHIP_ROLES[$relationshipType] ?? null;
        if ($roles === null) {
            return false;
        }

        if (!in_array($from['role'], $roles['from'], true)
            || !in_array($to['role'], $roles['to'], true)
        ) {
            return false;
        }

        unset($from, $to);
        $this->records[$fromExportId]['relationships'][] = $relationshipType . ':' . $toExportId;

        return true;
    }

    /**
     * Return the business ID bound to an export ID, or null when unknown or released.
     */
    public function businessIdForExportId(string $exportId): ?string
    {
        $record = $this->records[$exportId] ?? null;

        if ($record === null || $record['released']) {
            return null;
        }

        return $record['businessId'];
    }

    /**
     * Return the execution ID bound to an export ID, or null when unknown or released.
     */
    public function executionIdForExportId(string $exportId): ?string
    {
        $record = $this->records[$exportId] ?? null;

        if ($record === null || $record['released']) {
            return null;
        }

        return $record['executionId'];
    }

    /**
     * Find the export ID bound to a business ID within a given execution.
     *
     * Returns null when no unreleased record matches, meaning the relationship is
     * unverified and must not be trusted.
     */
    public function exportIdForBusinessId(string $businessId, string $executionId): ?string
    {
        $key = $businessId . "\0" . $executionId;
        $candidates = $this->byBusinessExecution[$key] ?? [];

        foreach ($candidates as $exportId) {
            $record = $this->records[$exportId] ?? null;
            if ($record !== null && !$record['released']) {
                return $exportId;
            }
        }

        return null;
    }

    /**
     * Find the export ID bound to a business ID, excluding a specific execution ID.
     *
     * Used when looking up a parent execution's export ID by business ID while
     * avoiding a self-match on the current execution. O(k) where k is the number
     * of records sharing the business ID (typically 1).
     */
    public function exportIdForBusinessIdExcluding(string $businessId, string $excludeExecutionId): ?string
    {
        $prefix = $businessId . "\0";

        foreach ($this->byBusinessExecution as $key => $candidates) {
            if (!str_starts_with($key, $prefix)) {
                continue;
            }

            $executionId = substr($key, strlen($prefix));
            if ($executionId === $excludeExecutionId) {
                continue;
            }

            foreach ($candidates as $exportId) {
                $record = $this->records[$exportId] ?? null;
                if ($record !== null && !$record['released'] && $record['executionId'] !== $excludeExecutionId) {
                    return $exportId;
                }
            }
        }

        return null;
    }

    /**
     * Check whether an execution has any live (unreleased) records.
     */
    public function hasLiveRecords(string $executionId): bool
    {
        $candidates = $this->byExecution[$executionId] ?? [];

        foreach ($candidates as $exportId) {
            $record = $this->records[$exportId] ?? null;
            if ($record !== null && !$record['released']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return all records, keyed by export ID. Used internally to look up bindings
     * by business ID. Records are immutable snapshots; callers must not mutate them.
     *
     * @return array<string, array{kind: string, role: string, executionId: string, businessId: string, relationships: list<string>, released: bool}>
     */
    public function allRecords(): array
    {
        return $this->records;
    }

    /**
     * Bind a locally created artifact to the owning execution and register the
     * execution→artifact relationship edge in one call. Replaces the duplicated
     * issueExportId()+exportIdForBusinessId()+registerRelationship() boilerplate
     * spread across runners, recorders, and factories.
     *
     * Returns the issued export ID, or null when issuance or the requested
     * relationship is rejected. Rejected relationships are reported without payloads.
     */
    public function bindArtifact(
        string $kind,
        string $role,
        string $executionId,
        string $businessId,
        string $relationshipType = '',
    ): ?string {
        if ($this->releasing) {
            return null;
        }

        if ($relationshipType !== '') {
            $executionExportId = $this->exportIdForBusinessId($executionId, $executionId);
            $execution = $executionExportId !== null ? ($this->records[$executionExportId] ?? null) : null;
            $roles = self::RELATIONSHIP_ROLES[$relationshipType] ?? null;
            if ($execution === null || $execution['released'] || $roles === null
                || !in_array($execution['role'], $roles['from'], true)
                || !in_array($role, $roles['to'], true)
            ) {
                $this->reportUnverified(new RuntimeException('Provenance relationship rejected.'));

                return null;
            }

            unset($execution);
        }

        $exportId = $this->issueExportId($kind, $role, $executionId, $businessId);

        if ($relationshipType !== ''
            && !$this->registerRelationship($executionExportId, $exportId, $relationshipType)
        ) {
            $this->reportUnverified(new RuntimeException('Provenance relationship rejected.'));

            return null;
        }

        return $exportId;
    }

    /**
     * Release all records for an execution after terminal publication and cleanup.
     *
     * Queued safe projections remain usable; late raw artifacts without a live
     * binding drop and report. Re-entry is guarded so concurrent interleaving
     * cannot partially release an independent execution.
     */
    public function releaseExecution(string $executionId): void
    {
        if ($this->releasing) {
            return;
        }

        $this->releasing = true;

        try {
            $candidates = $this->byExecution[$executionId] ?? [];

            foreach ($candidates as $exportId) {
                $record = $this->records[$exportId] ?? null;
                unset($this->records[$exportId]);
                // Remove from byBusinessExecution index directly via the record's
                // businessId/executionId key instead of scanning every bucket.
                if ($record !== null) {
                    $bizKey = $record['businessId'] . "\0" . $record['executionId'];
                    $cands = $this->byBusinessExecution[$bizKey] ?? [];
                    $pos = array_search($exportId, $cands, true);
                    if ($pos !== false) {
                        array_splice($this->byBusinessExecution[$bizKey], $pos, 1);
                        if ([] === $this->byBusinessExecution[$bizKey]) {
                            unset($this->byBusinessExecution[$bizKey]);
                        }
                    }
                }
            }

            unset($this->byExecution[$executionId]);
        } finally {
            $this->releasing = false;
        }
    }

    /**
     * Report an unverified or dropped artifact through the health channel as a
     * payload-free notice, then contain the failure.
     */
    public function reportUnverified(Throwable $failure, ?string $artifactId = null): void
    {
        if (!$this->health instanceof InternalHealthReporterInterface) {
            return;
        }

        try {
            $this->health->report(
                $failure,
                new RedactedArtifact([
                    ArtifactField::ID => $artifactId ?? 'redacted',
                    ArtifactField::TYPE => 'runit.artifact.redacted',
                ]),
            );
        } catch (Throwable $throwable) {
            // Contain health reporter failures so unverified drops stay fail-open.
            try {
                new FallbackErrorLogReporter()->report(
                    $failure,
                    $throwable,
                    $artifactId ?? 'redacted',
                );
            } catch (Throwable) {
                // No further independent sink remains; preserve the business outcome.
            }
        }
    }
}
