<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;
use ItPack\ConfigureIt\Behavior\CreatableBehavior;
use Nette\Schema\Message as NetteMessage;

class MessageFactory
{
    use CreatableBehavior;

    /**
     * @param list<int|string>    $path
     * @param array<string, mixed> $variables
     */
    public function build(
        string $message,
        string $code,
        array $path,
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