<?php

declare(strict_types=1);

namespace ItPack\RunIt\Common\Contract;

interface FactoryInterface
{
    public function build(): object;
}
