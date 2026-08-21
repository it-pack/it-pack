<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

/**
 * Non-intrusive observer for repository operations.
 *
 * The core repository and adapter must not depend on any concrete observer. This
 * port is applied via the {@see ObservedRepository} decorator in the Application
 * layer, keeping observation fully optional and decoupled.
 *
 * The vocabulary (operation name + structured context) is intentionally mappable
 * to RunIt's Activity/Observation/Occurrence triad without coupling CommitIt to
 * RunIt.
 *
 * @api
 */
interface RepositoryObserver
{
    /**
     * @param array<string, mixed> $context
     */
    public function onStart(string $operation, array $context = []): void;

    /**
     * @param array<string, mixed> $context
     */
    public function onSuccess(string $operation, array $context = []): void;

    /**
     * @param array<string, mixed> $context
     */
    public function onFailure(string $operation, \Throwable $error, array $context = []): void;
}
