<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Exception;

use ItPack\ConfigureIt\Contract\MessageInterface;
use ItPack\ConfigureIt\Contract\ValidationExceptionInterface;
use Throwable;

/**
 * Thrown when resolved configuration fails schema validation.
 */
class ValidationException extends ConfigurationException implements ValidationExceptionInterface
{
    /**
     * @param list<MessageInterface> $messages
     */
    public function __construct(
        string $message = '',
        ?int $code = null,
        ?Throwable $previous = null,
        private readonly array $messages = [],
    ) {
        parent::__construct($message, $code ?? 0, $previous);
    }

    /** @return list<string> */
    public function getMessages(): array
    {
        return array_map(static fn (MessageInterface $m): string => $m->toString(), $this->messages);
    }

    /** @return list<MessageInterface> */
    public function getMessageObjects(): array
    {
        return $this->messages;
    }
}
