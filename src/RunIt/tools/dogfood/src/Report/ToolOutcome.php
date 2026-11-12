<?php

declare(strict_types=1);

namespace Tools\Dogfood\Report;

use Tools\Dogfood\Process\ProcessResult;
use Tools\Dogfood\Tool\DevTool;

/**
 * The recorded outcome of one dogfood tool run.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class ToolOutcome
{
    public function __construct(
        public DevTool $tool,
        public ProcessResult $result,
    ) {
    }

    public function succeeded(): bool
    {
        return $this->result->succeeded();
    }
}
