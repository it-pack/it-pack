<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for artifacts that belong to exactly one execution.
 *
 * Ownership is expressed through identity, not through an in-memory owning
 * collection or a retained execution session. Every artifact must be able to
 * report the identifier of the execution it belongs to.
 *
 * The identifier is always an {@see ExecutionIdInterface}; because it extends
 * the shared string-identifier contract, callers that only need the string form
 * can rely on that contract without a separate downcast.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#executionId
 */
interface ExecutionOwnedInterface
{
    /**
     * Returns the identifier of the execution this artifact belongs to.
     */
    public function executionId(): ExecutionIdInterface;
}
