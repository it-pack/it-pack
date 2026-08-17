<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Activity;

use ItPack\RunIt\Contract\Domain\ActivityDefinitionInterface;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ActivityKindInterface;
use ItPack\RunIt\Contract\Domain\ActivityNameInterface;

/**
 * The immutable blueprint of an activity.
 *
 * The definition carries the name, kind, and optional parent activity identity. It is
 * the input to an activity runner and never changes as the activity runs.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Activity
 */
final readonly class ActivityDefinition implements ActivityDefinitionInterface
{
    private function __construct(
        private readonly ActivityNameInterface $name,
        private readonly ActivityKindInterface $kind,
        private readonly ?ActivityIdInterface $parentActivityId,
    ) {
    }

    public static function create(
        ActivityNameInterface $name,
        ActivityKindInterface $kind,
        ?ActivityIdInterface $parentActivityId = null,
    ): self {
        return new self($name, $kind, $parentActivityId);
    }

    public function name(): ActivityNameInterface
    {
        return $this->name;
    }

    public function kind(): ActivityKindInterface
    {
        return $this->kind;
    }

    public function parentActivityId(): ?ActivityIdInterface
    {
        return $this->parentActivityId;
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(): array
    {
        return [
            'name' => $this->name->toString(),
            'kind' => $this->kind->toString(),
            'parent_activity_id' => $this->parentActivityId?->toString(),
        ];
    }
}
