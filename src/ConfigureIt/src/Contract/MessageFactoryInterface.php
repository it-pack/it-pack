<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\Adapter\Message;
use Nette\Schema\Message as NetteMessage;

/**
 * Builds normalized {@see Message}s from raw templates or Nette schema messages.
 *
 * Extracted from {@see \ItPack\ConfigureIt\Adapter\MessageFactory} so the
 * Execution reporting spine depends on the contract, not the adapter concrete
 * (ADR-0008 — contract-first ports & adapters).
 */
interface MessageFactoryInterface
{
    /**
     * @param list<int|string>    $path
     * @param array<string, mixed> $variables
     */
    public function build(
        string $message,
        string $code,
        array $path,
        array $variables = [],
    ): Message;

    public function buildFromNetteMessage(NetteMessage $message): Message;
}
