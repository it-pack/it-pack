<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

use ItPack\DisenchantIt\ValueObject\CandidateInterface;

/**
 * Port for the detection orchestration: turn one PHP source string into the
 * grouped, named magic-value candidates worth promoting. The concrete
 * orchestrator (Core/root Disenchanter) composes the scanner, filter, grouper
 * and name generator behind this single seam.
 */
interface DisenchanterInterface
{
    /**
     * @return array<string, CandidateInterface> keyed by literal value.
     */
    public function detect(string $code): array;
}
