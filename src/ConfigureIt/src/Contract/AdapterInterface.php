<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

interface AdapterInterface
{
    public function getAdaptee(): object;
}
