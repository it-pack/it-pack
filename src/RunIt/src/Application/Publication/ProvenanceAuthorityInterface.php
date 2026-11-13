<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

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
}
