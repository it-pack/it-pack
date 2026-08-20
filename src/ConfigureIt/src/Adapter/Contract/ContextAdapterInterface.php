<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Contract;

use ItPack\ConfigureIt\Contract\ValueObject\ContextInterface;
use ItPack\ConfigureIt\Contract\ValueObject\AdapterInterface;
use Nette\Schema\Context as NetteContext;

interface ContextAdapterInterface extends ContextInterface, AdapterInterface
{
    public function getAdaptee(): NetteContext;
}