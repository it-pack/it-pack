<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\Naming;

/**
 * Port for turning a literal value into a constant name. Promoted out of
 * Detector so the naming policy is swappable/testable. Concrete adapter:
 * ConstantNameGenerator.
 */
interface ConstantNameGeneratorInterface
{
    public function generate(string $rawValue, ?string $fallbackHint = null): string;
}
