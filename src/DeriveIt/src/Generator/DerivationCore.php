<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ItPack\DeriveIt\Contract\Reflection\ReflectionCacheInterface;
use ItPack\DeriveIt\Contract\Telemetry\DeriveEventSinkInterface;
use ItPack\DeriveIt\Reflection\InMemoryReflectionCache;
use Nette\PhpGenerator\Factory;
use Nette\PhpGenerator\Method;
use ReflectionClass;
use ReflectionMethod;

/**
 * The shared derivation pipeline, composed into both concrete derivers
 * (REVIEW.md F-4 / R4-2 — replaces the former abstract base class).
 *
 * Owns the four cross-mode concerns: reflection memoization, skip/allow
 * filtering, substitutor seeding (self/static/parent + explicit map, A1), and
 * the signature pipeline. Also the single home of the telemetry emitter and
 * the source renderer, so derivers stay pure assembly logic.
 *
 * Construct directly or via {@see self::create()}; instances are immutable.
 */
final readonly class DerivationCore
{
    private MethodExtractor $extractor;
    private SourceRenderer $renderer;
    private MethodSignatureFactory $signatureFactory;
    private ReflectionCacheInterface $reflectionCache;
    private DeriveEventEmitter $events;

    public function __construct(
        private DeriveConfig $config = new DeriveConfig(),
        ReflectionCacheInterface $reflectionCache = new InMemoryReflectionCache(),
        ?MethodSignatureFactory $signatureFactory = null,
        ?Factory $factory = null,
        ?DeriveEventSinkInterface $eventSink = null,
    ) {
        $this->reflectionCache = $reflectionCache;
        $this->extractor = new MethodExtractor($config);
        $this->renderer = new SourceRenderer();
        $this->signatureFactory = $signatureFactory ?? new MethodSignatureFactory(factory: $factory, config: $config);
        $this->events = new DeriveEventEmitter($eventSink);
    }

    /**
     * Convenience factory mirroring the former abstract-base constructor shape
     * (config + shared cache + Nette factory + optional telemetry sink).
     */
    public static function create(
        DeriveConfig $config,
        ReflectionCacheInterface $reflectionCache,
        ?Factory $factory,
        ?DeriveEventSinkInterface $eventSink,
    ): self {
        return new self($config, $reflectionCache, null, $factory, $eventSink);
    }

    public function events(): DeriveEventEmitter
    {
        return $this->events;
    }

    public function config(): DeriveConfig
    {
        return $this->config;
    }

    public function renderer(): SourceRenderer
    {
        return $this->renderer;
    }

    public function extractor(): MethodExtractor
    {
        return $this->extractor;
    }

    /**
     * Reflects a class-name-or-instance, memoizing the {@see ReflectionClass} in
     * the injectable cache so whole-repo scans never reflect a type twice
     * (G18 / E5).
     */
    public function reflect(ReflectionClass|string $class): ReflectionClass
    {
        if ($class instanceof ReflectionClass) {
            return $class;
        }

        $cached = $this->reflectionCache->get($class);
        if ($cached !== null) {
            return $cached;
        }

        $reflection = new ReflectionClass($class);
        $this->reflectionCache->set($class, $reflection);

        return $reflection;
    }

    /**
     * Seeds the type-substitution map for `$reflection` (A1): when
     * `substituteSelfStatic` is on, the concrete FQCN and its parent FQCN map to
     * their derived interface FQCNs so `self`/`parent` are rewritten.
     *
     * @return array<string, string>
     */
    public function seedTypeMap(ReflectionClass $reflection): array
    {
        $typeMap = $this->config->typeSubstitutionMap;
        if ($this->config->substituteSelfStatic) {
            $typeMap[$reflection->getName()] = Naming::derivedInterfaceFqcn($reflection);
            $parent = $reflection->getParentClass();
            if ($parent !== false) {
                $typeMap[$parent->getName()] = Naming::derivedInterfaceFqcn($parent);
            }
        }

        return $typeMap;
    }

    /**
     * Builds the {@see TypeSubstitutor} for `$reflection`, mapping the
     * `self`/`static`/`parent` keywords plus the seeded concrete FQCNs (A1).
     */
    public function createTypeSubstitutor(ReflectionClass $reflection): TypeSubstitutor
    {
        $selfFqcn = $this->config->substituteSelfStatic ? Naming::derivedInterfaceFqcn($reflection) : null;
        $parent = $reflection->getParentClass();
        $parentFqcn = ($this->config->substituteSelfStatic && $parent !== false)
            ? Naming::derivedInterfaceFqcn($parent)
            : null;

        return new TypeSubstitutor($this->seedTypeMap($reflection), $selfFqcn, $parentFqcn);
    }

    /**
     * Builds the derived signature for one reflected method: applies skip/allow
     * filtering, the substitutor, and any configured rename. Returns null when
     * the method is filtered out. Single home of the extract → filter → rename
     * pipeline shared by flat and per-trait modes.
     */
    public function signatureFor(ReflectionMethod $method, TypeSubstitutor $substitutor): ?Method
    {
        if ($this->shouldSkipMethod($method->getName())) {
            return null;
        }

        $signature = $this->signatureFactory->fromMethod($method, $substitutor);

        $rename = $this->config->methodRenames[$method->getName()] ?? null;
        if ($rename !== null) {
            $signature = $signature->cloneWithName($rename);
        }

        return $signature;
    }

    private function shouldSkipMethod(string $name): bool
    {
        if (in_array($name, $this->config->skipMethods, true)) {
            return true;
        }

        if ($this->config->onlyMethods !== [] && !in_array($name, $this->config->onlyMethods, true)) {
            return true;
        }

        return false;
    }
}
