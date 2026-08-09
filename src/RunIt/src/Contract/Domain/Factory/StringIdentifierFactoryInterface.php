<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain\Factory;

use ItPack\RunIt\Common\Contract\FactoryInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;

interface StringIdentifierFactoryInterface extends FactoryInterface
{
    public function build(): StringIdentifierInterface;
}
