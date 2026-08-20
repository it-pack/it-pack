<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;
use ItPack\ConfigureIt\Behavior\CreatableBehavior;
use Nette\Schema\Message as NetteMessage;

class MessageFactory
{
    use CreatableBehavior;

    public function build(
         string $message,
         string $code,
        /** @var list<int|string> */
         array $path,
        /** @var array<string, mixed> */
         array $variables = [],
    ): Message
    {
        return $this->buildFromNetteMessage(
            new NetteMessage(
                $message,
                $code,
                $path,
                $variables
            )
        );
    }

    public function buildFromNetteMessage(NetteMessage $message): Message
    {
        return new Message($message);
    }
}