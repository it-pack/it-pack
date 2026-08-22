<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\ValueObject;

use ItPack\ConfigureIt\Behavior\CreatableBehavior;
use ItPack\ConfigureIt\Contract\ValueObject\ValidationResultFactoryInterface;

class ValidationResultFactory implements ValidationResultFactoryInterface
{
    use CreatableBehavior;

    /**
     * @param list<array{path: string, message: string, severity: 'error'|'warning'}> $issues
     */
    public function build(
        bool $valid,
        array $issues = [],
    ): ValidationResult {
        return new ValidationResult($valid, $issues);
    }
}
