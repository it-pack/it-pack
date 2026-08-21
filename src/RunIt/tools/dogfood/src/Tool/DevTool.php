<?php

declare(strict_types=1);

namespace Tools\Dogfood\Tool;

/**
 * A RunIt dev-tool that the dogfooding runner represents as an execution.
 *
 * Each tool maps to one composer script so the runner can invoke it as a
 * subprocess and record its outcome. The names, kinds, and occurrence/snapshot
 * types stay within the RunIt string-value rules so they serialize safely.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class DevTool
{
    public function __construct(
        public string $name,
        public string $command,
        public string $kind,
        public string $occurrenceType,
        public string $snapshotType,
    ) {
    }
}
