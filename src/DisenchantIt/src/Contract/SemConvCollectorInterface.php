<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

use ItPack\DisenchantIt\ValueObject\ConstantPlacementInterface;

/**
 * Port for building the shared SemConv interface source text from a set of
 * ConstantPlacements. Concrete adapter: SemConvCollector.
 */
interface SemConvCollectorInterface
{
    /**
     * @param list<ConstantPlacementInterface> $placements across the WHOLE scan, not just one file
     * @param string|null $existingSource the CURRENT content of the SemConv file, if one
     *        already exists from a prior run — passed in explicitly (rather than this class
     *        reading the filesystem itself) to keep it a pure string-in/string-out transform.
     */
    public function collect(array $placements, ?string $existingSource = null): string;
}
