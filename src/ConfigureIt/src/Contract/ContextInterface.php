<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

interface ContextInterface
{
    /** @param  array<string, mixed>  $variables */
    public function addError(string $message, string $code, array $variables = []): MessageInterface;


    /** @param  array<string, mixed>  $variables */
    public function addWarning(string $message, string $code, array $variables = []): MessageInterface;


    /** @return \Closure(): bool */
    public function createChecker(): \Closure;

    /**
     * Returns the underlying context implementation (adapter-specific).
     *
     * @return object
     */
    public function getAdaptee(): object;
}