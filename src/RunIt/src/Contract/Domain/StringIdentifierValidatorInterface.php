<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

interface StringIdentifierValidatorInterface
{
    public function validate(StringIdentifierInterface|string $identifier): true;
}
