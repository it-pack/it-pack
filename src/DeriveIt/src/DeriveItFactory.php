<?php

declare(strict_types=1);

namespace ItPack\DeriveIt;

use ItPack\DeriveIt\Contract\Generator\AdapterGeneratorInterface;
use ItPack\DeriveIt\Contract\Generator\FamilyDeriverInterface;
use ItPack\DeriveIt\Contract\Reflection\ReflectionCacheInterface;
use ItPack\DeriveIt\Contract\Telemetry\DeriveEventSinkInterface;
use ItPack\DeriveIt\Generator\AdapterGenerator;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\FamilyDeriver;
use ItPack\DeriveIt\Generator\InterfaceDeriver;
use ItPack\DeriveIt\Generator\PerTraitInterfaceDeriver;
use ItPack\DeriveIt\Reflection\InMemoryReflectionCache;
use Nette\PhpGenerator\Factory;
use Nette\PhpGenerator\InterfaceType;
use ReflectionClass;

/**
 * Composition-root factory for the derivers (F2 / R.2). Assembles the four derivers
 * behind their interfaces, optionally sharing one {@see ReflectionCacheInterface}
 * and one Nette {@see Factory} across the whole set so a CLI run reflects / builds
 * each type at most once. Lives in the Entry layer — deptrac already allows Entry to
 * depend on Generator + Contract — so no `deptrac.yaml` change is required.
 */
final class DeriveItFactory
{
    private readonly ReflectionCacheInterface $reflectionCache;
    private readonly ?Factory $factory;
    private readonly ?DeriveEventSinkInterface $eventSink;

    public function __construct(
        private readonly DeriveConfig $config = new DeriveConfig(),
        ?ReflectionCacheInterface $reflectionCache = null,
        ?Factory $factory = null,
        ?DeriveEventSinkInterface $eventSink = null,
    ) {
        $this->reflectionCache = $reflectionCache ?? new InMemoryReflectionCache();
        $this->factory = $factory;
        $this->eventSink = $eventSink;
    }

    public function interfaceDeriver(): InterfaceDeriver
    {
        return new InterfaceDeriver(config: $this->config, factory: $this->factory, reflectionCache: $this->reflectionCache, eventSink: $this->eventSink);
    }

    public function perTraitDeriver(): PerTraitInterfaceDeriver
    {
        return new PerTraitInterfaceDeriver(config: $this->config, factory: $this->factory, reflectionCache: $this->reflectionCache, eventSink: $this->eventSink);
    }

    public function familyDeriver(): FamilyDeriverInterface
    {
        return new FamilyDeriver($this->config, $this->factory, $this->reflectionCache, $this->eventSink);
    }

    /**
     * @param array<string, string> $returnWrapperMap Derived-interface FQCN => adapter class FQCN.
     */
    public function adapterGenerator(InterfaceType $interface, ReflectionClass $adaptee, array $returnWrapperMap = []): AdapterGeneratorInterface
    {
        return new AdapterGenerator($interface, $adaptee, $returnWrapperMap);
    }
}
