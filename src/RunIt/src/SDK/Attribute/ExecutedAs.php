<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Attribute;

use Attribute;

/**
 * Declares that a function, method, or invokable class should be executed as a RunIt execution.
 *
 * The attribute carries metadata only. It never instruments code by itself: a reader resolves
 * it (for example when wrapping a callable through the SDK facade) and the execution runner
 * performs the actual wrapping. Keep the values stable and lowercase so they remain compatible
 * with the execution-name and execution-kind validation rules.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
#[Attribute(flags: Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final readonly class ExecutedAs implements ExecutedAsInterface
{
    public function __construct(
        public string $name,
        public string $kind = self::DEFAULT_KIND,
    ) {
    }
}
