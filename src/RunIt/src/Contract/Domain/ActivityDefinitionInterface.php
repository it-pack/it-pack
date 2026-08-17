<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for the immutable blueprint of an activity.
 *
 * A definition carries the name, kind, and optional parent activity identity. It is
 * the input to an activity runner and never changes as the activity runs.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Activity
 */
interface ActivityDefinitionInterface extends NormalizableInterface
{
    public function name(): ActivityNameInterface;

    public function kind(): ActivityKindInterface;

    /**
     * Returns the parent activity identifier, or null when this is a top-level activity.
     */
    public function parentActivityId(): ?ActivityIdInterface;
}
