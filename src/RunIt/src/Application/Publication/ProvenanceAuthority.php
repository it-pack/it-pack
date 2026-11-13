<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use InvalidArgumentException;
use ItPack\RunIt\Application\Factory\RedactedRedactedArtifactFactory;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactedArtifactFactoryInterface;
use ItPack\RunIt\Contract\Domain\ArtifactType;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use Random\RandomException;
use RuntimeException;
use Throwable;
use WeakMap;

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
 * Concurrency: records and their relationship lists are mutated by the authority.
 * The authority is safe for use from a single persistent worker.
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
        'activity-artifact' => [
            'from' => ['activity'],
            'to' => ['observation', 'occurrence', 'measurement', 'snapshot', 'terminal-snapshot'],
        ],
        'activity-parent' => [
            'from' => ['activity'],
            'to' => ['activity'],
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

    /** @var array<string, array<string, array<string, true>>> */
    private array $outgoing = [];

    /** @var array<string, array<string, array<string, true>>> */
    private array $incoming = [];

    private bool $releasing = false;

    private int $edgeCount = 0;

    /** @var WeakMap<ExecutionArtifactInterface, array{exportId: string, fields: array<string, mixed>}> */
    private WeakMap $evidence;

    private readonly RedactedArtifactFactoryInterface $redactedFactory;

    public function __construct(
        private readonly ?InternalHealthReporterInterface $health = null,
        private readonly int $maxRecords = 100000,
        private readonly int $maxEdges = 200000,
        private readonly int $maxIdentifierBytes = 1024,
        private readonly int $maxTotalRecords = 100000,
        ?RedactedArtifactFactoryInterface $redactedFactory = null,
    ) {
        if ($maxRecords < 1 || $maxEdges < 1 || $maxIdentifierBytes < 1 || $maxTotalRecords < 1) {
            throw new InvalidArgumentException('Provenance retention limits must be positive.');
        }

        $this->evidence = new WeakMap();
        $this->redactedFactory =
            $redactedFactory?->withArtifactType(ArtifactType::UNKNOWN)
            ?? new RedactedRedactedArtifactFactory(ArtifactType::UNKNOWN);
    }

    /** Bind evidence to the exact object created by an SDK issuance site. */
    public function attest(ExecutionArtifactInterface $artifact, ?string $exportId): bool
    {
        if ($exportId === null || !isset($this->records[$exportId])) {
            return false;
        }

        try {
            $fields = $this->identityFields($artifact);
            $record = $this->records[$exportId];
            $executionId = $record['role'] === 'execution'
                ? ($fields[ArtifactField::ID] ?? null)
                : ($fields[ArtifactField::EXECUTION_ID] ?? null);
            if ($executionId !== $record['executionId']
                || ($record['role'] !== 'measurement'
                    && ($fields[ArtifactField::ID] ?? null) !== $record['businessId'])) {
                return false;
            }

            $this->evidence[$artifact] = ['exportId' => $exportId, 'fields' => $fields];
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /** Return the issued export ID only for the unchanged, live object. */
    public function exportIdForArtifact(ExecutionArtifactInterface $artifact): ?string
    {
        $evidence = $this->evidence[$artifact] ?? null;
        if ($evidence === null || !isset($this->records[$evidence['exportId']])) {
            return null;
        }

        try {
            if ($evidence['fields'] !== $this->identityFields($artifact)) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return $evidence['exportId'];
    }

    /** Transfer an existing issuance to an immutable lifecycle successor. */
    public function attestSuccessor(ExecutionArtifactInterface $previous, ExecutionArtifactInterface $successor): bool
    {
        $exportId = $this->exportIdForArtifact($previous);
        if ($exportId === null) {
            return false;
        }

        try {
            if ($this->identityFields($previous) !== $this->identityFields($successor)) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        return $this->attest($successor, $exportId);
    }

    /**
     * Return export identities for attested objects and recorded edges only.
     * Unverified activity and predecessor claims are deliberately omitted.
     *
     * @return array<string, mixed>|null
     */
    public function exportIdentityFields(ExecutionArtifactInterface $artifact): ?array
    {
        $exportId = $this->exportIdForArtifact($artifact);
        if ($exportId === null) {
            return null;
        }

        $record = $this->records[$exportId];
        $fields = $this->evidence[$artifact]['fields'];
        $output = [];
        if (array_key_exists(ArtifactField::ID, $fields)) {
            $output[ArtifactField::ID] = $exportId;
        }

        if ($record['role'] !== 'execution') {
            $rootId = $this->exportIdForBusinessId($record['executionId'], $record['executionId']);
            $root = $rootId !== null ? ($this->records[$rootId] ?? null) : null;
            $relation = $record['role'] === 'terminal-snapshot'
                ? 'execution-terminal-snapshot' : 'execution-' . $record['role'];
            if ($root === null || $root['role'] !== 'execution'
                || !isset($this->outgoing[$rootId][$relation][$exportId])) {
                return null;
            }

            $output[ArtifactField::EXECUTION_ID] = $rootId;

            return $output + $this->activityIdentityFields($exportId, $fields);
        }

        $parents = [];
        $links = [];
        $linkedClaims = $fields[ArtifactField::LINKED_EXECUTION_IDS] ?? [];
        if (!is_array($linkedClaims)) {
            return null;
        }

        foreach ($this->incoming[$exportId]['parent'] ?? [] as $sourceId => $_) {
            $source = $this->records[$sourceId] ?? null;
            if ($source !== null && $source['role'] === 'execution'
                && ($fields[ArtifactField::PARENT_EXECUTION_ID] ?? null) === $source['businessId']) {
                $parents[] = $sourceId;
            }
        }

        $links = $this->linkedExecutionIds($exportId, $linkedClaims);

        if (count($parents) === 1) {
            $output[ArtifactField::PARENT_EXECUTION_ID] = $parents[0];
        }

        if ($links !== []) {
            $output[ArtifactField::LINKED_EXECUTION_IDS] = $links;
        }

        return $output;
    }

    /**
     * Mint an opaque export ID and bind it to a locally created artifact.
     *
     * The export ID is independent of the caller-supplied business ID and carries
     * no meaning outside this authority. The binding records kind, role, owning
     * execution, and the artifact's own business ID so verification can reject
     * copied, substituted, or forged evidence.
     *
     * Records are bounded both per execution and across this authority. At either
     * limit issuance fails; bindArtifact drops and reports the rejected binding.
     *
     * @param list<string> $relationships Initial relationships are unsupported and must be empty.
     * @throws RandomException
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

        if (
            $relationships !== []
            || count($this->records) >= $this->maxTotalRecords
            || count($this->byExecution[$executionId] ?? []) >= $this->maxRecords
            || strlen($kind) > $this->maxIdentifierBytes || strlen($role) > $this->maxIdentifierBytes
            || strlen($executionId) > $this->maxIdentifierBytes || strlen($businessId) > $this->maxIdentifierBytes
        ) {
            throw new RuntimeException('Provenance capacity exceeded or initial relationships rejected.');
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

        if (
            !in_array($from['role'], $roles['from'], true)
            || !in_array($to['role'], $roles['to'], true)
        ) {
            return false;
        }

        if (
            ($from['executionId'] !== $to['executionId'] || $fromExportId === $toExportId)
            && str_starts_with($relationshipType, 'activity-')
        ) {
            return false;
        }

        if (isset($this->outgoing[$fromExportId][$relationshipType][$toExportId])) {
            return true;
        }

        if ($this->edgeCount >= $this->maxEdges) {
            $this->reportUnverified(new RuntimeException('Provenance relationship capacity exceeded.'));

            return false;
        }

        unset($from, $to);
        $this->records[$fromExportId]['relationships'][] = $relationshipType . ':' . $toExportId;
        $this->outgoing[$fromExportId][$relationshipType][$toExportId] = true;
        $this->incoming[$toExportId][$relationshipType][$fromExportId] = true;
        ++$this->edgeCount;

        return true;
    }

    /**
     * Record a verified relationship between two issued export IDs.
     *
     * Both endpoints must be issued and unreleased export IDs. Business IDs are
     * not accepted here: callers must resolve activity business IDs to export IDs
     * through {@see exportIdForBusinessId()} before calling, so that only
     * independently issued and verified identities can be bound.
     *
     * @param string|null $targetId Export ID of the target artifact (not a business ID)
     * @param string|null $activityId Export ID of the activity (not a business ID)
     */
    public function bindActivityRelationship(
        ?string $targetId = null,
        ?string $activityId = null,
        bool $parent = false,
    ): void {
        if ($targetId === null || $activityId === null) {
            return;
        }

        if (!isset($this->records[$targetId]) || !isset($this->records[$activityId])) {
            return;
        }

        $target = $this->records[$targetId];
        $activity = $this->records[$activityId];

        if ($target['released'] || $activity['released']) {
            return;
        }

        if ($target['executionId'] !== $activity['executionId']) {
            return;
        }

        $relationshipType = $parent ? 'activity-parent' : 'activity-artifact';

        $this->registerRelationship($activityId, $targetId, $relationshipType);
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
     * unverified and must not be trusted. When `$role` is provided, only records
     * of that role are eligible, so a measurement with business ID "2" cannot
     * shadow an activity with business ID "2" in the same execution.
     *
     * @param string|null $role Optional role filter (e.g. 'activity')
     */
    public function exportIdForBusinessId(string $businessId, string $executionId, ?string $role = null): ?string
    {
        $key = $businessId . "\0" . $executionId;
        $candidates = $this->byBusinessExecution[$key] ?? [];

        foreach ($candidates as $exportId) {
            $record = $this->records[$exportId] ?? null;
            if ($record !== null && !$record['released'] && ($role === null || $record['role'] === $role)) {
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
                || $this->edgeCount >= $this->maxEdges
            ) {
                $this->reportUnverified(new RuntimeException('Provenance relationship rejected.'));

                return null;
            }

            unset($execution);
        }

        try {
            $exportId = $this->issueExportId($kind, $role, $executionId, $businessId);
        } catch (Throwable) {
            $this->reportUnverified(new RuntimeException('Provenance issuance rejected.'));

            return null;
        }

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
                foreach ($this->outgoing[$exportId] ?? [] as $type => $targets) {
                    foreach ($targets as $target => $_) {
                        unset($this->incoming[$target][$type][$exportId]);
                        --$this->edgeCount;
                    }
                }

                foreach ($this->incoming[$exportId] ?? [] as $type => $sources) {
                    foreach ($sources as $source => $_) {
                        unset($this->outgoing[$source][$type][$exportId]);
                        $edge = $type . ':' . $exportId;
                        $this->records[$source]['relationships'] = array_values(array_filter(
                            $this->records[$source]['relationships'],
                            static fn (string $item): bool => $item !== $edge,
                        ));
                        --$this->edgeCount;
                    }
                }

                unset($this->outgoing[$exportId], $this->incoming[$exportId], $this->records[$exportId]);
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
     *
     * When no health reporter is configured (standalone composition), the notice
     * is written through the payload-free error-log fallback so the drop remains
     * observable instead of being silently dropped.
     */
    public function reportUnverified(Throwable $failure, ?string $artifactId = null): void
    {
        $artifactId ??= 'redacted';

        if ($this->health instanceof InternalHealthReporterInterface) {
            try {
                $this->health->report(
                    $failure,
                    $this->redactedFactory->create([
                        ArtifactField::ID => $artifactId,
                        ArtifactField::TYPE => 'runit.artifact.redacted',
                    ])
                );

                return;
            } catch (Throwable) {
                // Contain health reporter failures so unverified drops stay fail-open.
            }
        }

        // No health reporter configured, or it failed: report through the
        // payload-free error-log fallback so standalone composition stays observable.
        try {
            new FallbackErrorLogReporter()->report(
                $failure,
                new RuntimeException('no health reporter configured'),
                $artifactId,
            );
        } catch (Throwable) {
            // No further independent sink remains; preserve the business outcome.
        }
    }

    /** @param array<string, mixed> $fields
     * @return array<string, string>
     */
    private function activityIdentityFields(string $exportId, array $fields): array
    {
        $record = $this->records[$exportId];
        $field = $record['role'] === 'activity' ? ArtifactField::PARENT_ACTIVITY_ID : ArtifactField::ACTIVITY_ID;
        $relation = $record['role'] === 'activity' ? 'activity-parent' : 'activity-artifact';
        $claim = $fields[$field] ?? null;
        $matches = [];
        foreach ($this->incoming[$exportId][$relation] ?? [] as $sourceId => $_) {
            $source = $this->records[$sourceId] ?? null;
            if ($source !== null && $source['executionId'] === $record['executionId']
                && $source['businessId'] === $claim) {
                $matches[] = $sourceId;
            }
        }

        return count($matches) === 1 ? [$field => $matches[0]] : [];
    }

    /** @param array<mixed> $claims
     * @return list<string>
     */
    private function linkedExecutionIds(string $exportId, array $claims): array
    {
        $lookup = array_fill_keys(array_filter($claims, is_string(...)), true);
        $links = [];
        foreach ($this->incoming[$exportId]['link'] ?? [] as $sourceId => $_) {
            $source = $this->records[$sourceId] ?? null;
            if ($source !== null && $source['role'] === 'execution' && isset($lookup[$source['businessId']])) {
                $links[] = $sourceId;
            }
        }

        return $links;
    }

    /** @return array<string, mixed> */
    private function identityFields(ExecutionArtifactInterface $artifact): array
    {
        return array_intersect_key($artifact->normalize(), array_flip([
            ArtifactField::ID, ArtifactField::EXECUTION_ID, ArtifactField::ACTIVITY_ID,
            ArtifactField::PARENT_EXECUTION_ID, ArtifactField::LINKED_EXECUTION_IDS,
            ArtifactField::PARENT_ACTIVITY_ID, ArtifactField::PREDECESSOR_ID,
        ]));
    }
}
