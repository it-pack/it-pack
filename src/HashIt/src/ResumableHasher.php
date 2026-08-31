<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use InvalidArgumentException;

/**
 * Reference implementation of {@see \ItPack\HashIt\Contract\ResumableHashingInterface}, built only on the
 * {@see \ItPack\HashIt\Contract\ContextFactoryInterface} port. Checkpoints are self-describing envelopes
 * ({@see ResumableHash::checkpoint()}) carrying the algorithm + binary flag plus
 * the serialized native context; resume rebuilds the context through
 * {@see \ItPack\HashIt\Contract\ContextFactoryInterface::restore()}.
 *
 * Core depends on nothing but itself (deptrac-clean). HMAC resumption is not
 * offered — the native HMAC context cannot be serialized (docs/ADR-0002).
 */
final readonly class ResumableHasher implements \ItPack\HashIt\Contract\ResumableHashingInterface
{
    public function __construct(private \ItPack\HashIt\Contract\ContextFactoryInterface $factory)
    {
    }

    public function start(string|HashAlgorithm $algorithm = HashAlgorithm::SHA256, bool $binary = false): \ItPack\HashIt\Contract\ResumableHashInterface
    {
        $name = $algorithm instanceof HashAlgorithm ? $algorithm->value : $algorithm;

        return new ResumableHash($this->factory->build($name), $name, $binary);
    }

    public function resume(string $checkpoint): \ItPack\HashIt\Contract\ResumableHashInterface
    {
        $decoded = $this->decode($checkpoint);

        return new ResumableHash($this->factory->restore($decoded['state']), $decoded['algorithm'], $decoded['binary']);
    }

    /**
     * @return array{algorithm: string, binary: bool, state: string}
     */
    private function decode(string $checkpoint): array
    {
        $data = @unserialize($checkpoint, ['allowed_classes' => false]);

        if (
            !is_array($data)
            || !isset($data['algorithm'], $data['state'])
            || !is_string($data['algorithm'])
            || !is_string($data['state'])
            || !is_bool($data['binary'] ?? false)
        ) {
            throw new InvalidArgumentException('Checkpoint is not a valid resumable-hash envelope');
        }

        return ['algorithm' => $data['algorithm'], 'binary' => $data['binary'], 'state' => $data['state']];
    }
}
