<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Codegen;

use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureIt\Exception\ConfigurationException;

/**
 * Hydrates a codegen-produced DTO from a snapshot.
 *
 * The DTOs emitted by {@see ConfigCodegen} expose a static `fromSnapshot()`
 * factory and implement {@see GeneratedConfigDtoInterface}; this adapter invokes
 * the factory, keeping the call site free of reflection and static-analysis
 * suppression (T1.3.3).
 *
 * @see docs/architecture/MODEL.md "Typed Snapshot" (codegen companion, E2.3)
 */
final class ConfigDtoHydrator
{
    public function hydrate(string $dtoClass, ConfigurationSnapshotInterface $snapshot): GeneratedConfigDtoInterface
    {
        if (!class_exists($dtoClass)) {
            throw new ConfigurationException(sprintf('DTO class "%s" is not loaded; generate it with ConfigCodegen first.', $dtoClass));
        }

        if (!method_exists($dtoClass, 'fromSnapshot')) {
            throw new ConfigurationException(sprintf('DTO class "%s" does not expose a fromSnapshot() factory.', $dtoClass));
        }

        /** @var GeneratedConfigDtoInterface $dto */
        $dto = $dtoClass::fromSnapshot($snapshot);

        return $dto;
    }
}
