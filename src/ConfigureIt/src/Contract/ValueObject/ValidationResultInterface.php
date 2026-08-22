<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract\ValueObject;

interface ValidationResultInterface
{
    public function valid(): bool;

    /**
     * All collected issues (errors and warnings), each carrying its severity.
     *
     * @return list<array{path: string, message: string, severity: 'error'|'warning'}>
     */
    public function issues(): array;

    /**
     * Blocking issues (severity `error`).
     *
     * @return list<array{path: string, message: string, severity: 'error'|'warning'}>
     */
    public function errors(): array;

    /**
     * Non-blocking issues (severity `warning`).
     *
     * @return list<array{path: string, message: string, severity: 'error'|'warning'}>
     */
    public function warnings(): array;

    public function hasErrors(): bool;

    public function hasWarnings(): bool;
}
