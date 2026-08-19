<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Port;

/**
 * Contract for a publisher that owns a transport and must be closed.
 *
 * Closing releases connections, files, or stream resources. Closing a publisher
 * that has buffered artifacts should flush them according to the publisher's
 * shutdown budget before releasing the transport.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface CloseablePublisherInterface
{
    /**
     * Releases the publisher's transport resources.
     */
    public function close(): void;
}
