<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Contract\MessageInterface;
use Nette\Schema\Message as NetteMessage;

class Message implements MessageInterface
{
    public function __construct(
        public readonly NetteMessage $adaptee
    ){}

    public function toString(): string
    {
        return $this->adaptee->toString();
    }

    public function __toString()
    {
        return $this->toString();
    }
}