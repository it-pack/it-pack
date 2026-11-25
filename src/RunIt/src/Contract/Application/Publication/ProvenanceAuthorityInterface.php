<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application\Publication;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use Random\RandomException;
use Throwable;

/**
 * Contract for issuance and verification of SDK-controlled export identities.
 *
 * @internal provenance authority; not a public contract.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ProvenanceAuthorityInterface
{
    /**
     * Mint an opaque export ID bound to a locally created artifact.
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
    ): string;

    /**
     * Verify that an export ID is bound to the expected role and execution.
     */
    public function verifyExportId(string $exportId, string $role, string $executionId): bool;

    /**
     * Return the business ID bound to an export ID, or null when unknown or released.
     */
    public function businessIdForExportId(string $exportId): ?string;

    /**
     * Return the execution ID bound to an export ID, or null when unknown or released.
     */
    public function executionIdForExportId(string $exportId): ?string;

    /**
     * Find the export ID bound to a business ID within a given execution.
     */
    public function exportIdForBusinessId(string $businessId, string $executionId): ?string;

    /**
     * Record a verified relationship edge between two issued export IDs.
     */
    public function registerRelationship(string $fromExportId, string $toExportId, string $relationshipType): bool;

    /**
     * Check whether an execution has any live (unreleased) records.
     */
    public function hasLiveRecords(string $executionId): bool;

    /**
     * Release all records for an execution after terminal publication and cleanup.
     */
    public function releaseExecution(string $executionId): void;

    /**
     * Report an unverified or dropped artifact through the health channel.
     */
    public function reportUnverified(Throwable $failure, ?string $artifactId = null): void;

    /**
     * Bind evidence to the exact object created by an SDK issuance site.
     */
    public function attest(ExecutionArtifactInterface $artifact, ?string $exportId): bool;

    /**
     * Return the issued export ID only for the unchanged, live object.
     */
    public function exportIdForArtifact(ExecutionArtifactInterface $artifact): ?string;

    /**
     * Transfer an existing issuance to an immutable lifecycle successor.
     */
    public function attestSuccessor(ExecutionArtifactInterface $previous, ExecutionArtifactInterface $successor): bool;

    /**
     * Return export identities for attested objects and recorded edges only.
     *
     * @return array<string, mixed>|null
     */
    public function exportIdentityFields(ExecutionArtifactInterface $artifact): ?array;

    /**
     * Record a verified relationship between two issued export IDs using activity export IDs.
     *
     * @param string|null $targetId     Export ID of the target artifact (not a business ID)
     * @param string|null $activityId   Export ID of the activity (not a business ID)
     */
    public function bindActivityRelationship(
        ?string $targetId = null,
        ?string $activityId = null,
        bool $parent = false,
    ): void;

    /**
     * Find the export ID bound to a business ID, excluding a specific execution ID.
     */
    public function exportIdForBusinessIdExcluding(string $businessId, string $excludeExecutionId): ?string;

    /**
     * Return all records, keyed by export ID.
     *
     * @return array<string, array{kind: string, role: string, executionId: string, businessId: string, relationships: list<string>, released: bool}>
     */
    public function allRecords(): array;

    /**
     * Bind a locally created artifact to the owning execution and register the
     * execution-to-artifact relationship edge in one call.
     *
     * Returns the issued export ID, or null when issuance or the requested
     * relationship is rejected.
     */
    public function bindArtifact(
        string $kind,
        string $role,
        string $executionId,
        string $businessId,
        string $relationshipType = '',
    ): ?string;
}
