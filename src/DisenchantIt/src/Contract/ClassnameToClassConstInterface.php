<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

use ItPack\DisenchantIt\ValueObject\GroupedCandidateInterface;
use ItPack\DisenchantIt\ValueObject\PromotionArtifactInterface;

/**
 * Promotion target that rewrites a fully-qualified class-name string
 * (`'App\Foo\Bar'`) to a `<Short>::class` reference. Opt-in only: active solely
 * when the scope enables `convertFqcnToClassConst`; it emits no constant
 * declaration (a `::class` constant is already provided by the engine).
 *
 * Extends PromotionTargetInterface so it slots into the composite promotion
 * seam alongside ClassConstantTarget / BackedEnumTarget / FlagSetTarget.
 */
interface ClassnameToClassConstInterface extends PromotionTargetInterface
{
    public function supports(GroupedCandidateInterface $group): bool;

    public function build(GroupedCandidateInterface $group): PromotionArtifactInterface;
}
