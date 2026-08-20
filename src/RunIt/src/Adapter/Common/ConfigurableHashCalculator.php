<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Common;

use InvalidArgumentException;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;

/**
 * Configurable deterministic content-hash calculator for snapshot payloads.
 *
 * The algorithm is adapter-determined and validated against the PHP `hash`
 * extension at construction, defaulting to SHA-256. The domain continues to
 * depend only on {@see HashCalculatorInterface}; this adapter merely makes the
 * algorithm explicit and configurable so a deployment can document its choice.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
final class ConfigurableHashCalculator implements HashCalculatorInterface
{
    public function __construct(
        private readonly string $algorithm = 'sha256',
    ) {
        if (!in_array($this->algorithm, hash_algos(), true)) {
            throw new InvalidArgumentException(sprintf('Unsupported hash algorithm "%s".', $this->algorithm));
        }
    }

    public function calculate(string $content): string
    {
        return hash($this->algorithm, $content);
    }
}
