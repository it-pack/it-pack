<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

/**
 * Port for assembling the detection half: the nikic/AST scanner plus the
 * candidate filter and grouper it is normally composed with. The concrete
 * builder lives in the Factory namespace and is the composition root that
 * injects each collaborator, so no participant `new`s its own dependencies.
 */
interface DetectorFactoryInterface
{
    public function build(): StringLiteralDetectorInterface;

    public function buildFilter(): CandidateFilterInterface;

    public function buildGrouper(): CandidateGrouperInterface;
}
