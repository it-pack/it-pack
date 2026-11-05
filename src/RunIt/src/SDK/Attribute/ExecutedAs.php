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
#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final  class ExecutedAs implements ExecutedAsInterface
{
    public string $name{
        get {
            return $this->_name;
        }
    }

    public string $kind{
        get {
            return $this->_kind;
        }
    }

    public function __construct(
        private readonly string $_name,
        private readonly ?string $_kind = self::DEFAULT_KIND,
    ) {}
}
