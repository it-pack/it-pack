<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Adapter\Contract\ContextAdapterInterface;
use ItPack\ConfigureIt\Contract\MessageInterface;
use Nette\Schema\Context as NetteContext;

final readonly class Context implements ContextAdapterInterface
{
    public function __construct(
        public NetteContext    $adaptee,
        private MessageFactory $messageFactory = new MessageFactory(),
    ){}

    public function addError(string $message, string $code, array $variables = []): MessageInterface
    {
        return $this->messageFactory->buildFromNetteMessage(
            $this->adaptee->addError($message, $code, $variables)
        );
    }

    public function addWarning(string $message, string $code, array $variables = []): MessageInterface
    {
        return $this->messageFactory->buildFromNetteMessage(
            $this->adaptee->addWarning($message, $code, $variables)
        );
    }

    public function createChecker(): \Closure
    {
        return $this->adaptee->createChecker();
    }

    public function getAdaptee(): NetteContext
    {
        return $this->adaptee;
    }
}