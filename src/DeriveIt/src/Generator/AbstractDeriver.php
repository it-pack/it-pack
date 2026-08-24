<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ItPack\DeriveIt\Contract\Generator\DeriverInterface;
use ItPack\DeriveIt\Contract\Reflection\ReflectionCacheInterface;
use ItPack\DeriveIt\Reflection\InMemoryReflectionCache;
use Nette\PhpGenerator\Factory;
use Nette\PhpGenerator\Method;
use ReflectionClass;
use ReflectionMethod;

/**
 * Shared base for the interface-deriving strategies ({@see InterfaceDeriver} flat
 * mode, {@see PerTraitInterfaceDeriver} per-trait mode).
 *
 * Extracts the duplicated deriver plumbing so the two modes stay in lockstep:
 *  - substitutor seeding (self/static/parent keyword mapping plus concrete class /
 *    parent FQCN rewriting — A1) via {@see createTypeSubstitutor()} / {@see seedTypeMap()};
 *  - the uniform skip/allow method filter via {@see shouldSkipMethod()};
 *  - reflection memoization via {@see reflect()} backed by an injectable
 *    {@see ReflectionCacheInterface} (G18 / E5).
 *
 * @internal Deriver implementations extend this; do not use directly.
 */
abstract readonly class AbstractDeriver implements DeriverInterface
{
    protected MethodExtractor $extractor;
    protected SourceRenderer $renderer;
    protected MethodSignatureFactory $signatureFactory;

    public function __construct(
        ?MethodSignatureFactory     $signatureFactory = null,
        protected DeriveConfig       $config = new DeriveConfig(),
        ?Factory                     $factory = null,
        protected ReflectionCacheInterface $reflectionCache = new InMemoryReflectionCache(),
    ) {
        $this->extractor = new MethodExtractor($config);
        $this->renderer = new SourceRenderer();
        $this->signatureFactory = $signatureFactory ?? new MethodSignatureFactory(factory: $factory, config: $config);
    }

    /**
     * Reflects a class-name-or-instance, memoizing the {@see ReflectionClass} in the
     * injectable cache so whole-repo scans never reflect (or `class_exists`) a type
     * twice (G18 / E5).
     */
    protected function reflect(ReflectionClass|string $class): ReflectionClass
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

    protected function shouldSkipMethod(string $name): bool
    {
        if (in_array($name, $this->config->skipMethods, true)) {
            return true;
        }

        if ($this->config->onlyMethods !== [] && !in_array($name, $this->config->onlyMethods, true)) {
            return true;
        }

        return false;
    }

    /**
     * Builds the derived signature for one reflected method: applies skip/allow
     * filtering, the substitutor, and any configured rename. Returns null when
     * the method is filtered out. Shared by the flat deriver's build loop and
     * both per-trait loops so the extract → filter → rename pipeline exists once.
     */
    protected function signatureFor(ReflectionMethod $method, TypeSubstitutor $substitutor): ?Method
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

    /**
     * Seeds the type-substitution map for `$reflection`: when `substituteSelfStatic`
     * is on, the concrete class FQCN and its parent FQCN are mapped to their derived
     * interface FQCNs so that `self` (which reflection resolves to the concrete name)
     * and `parent` types are rewritten (A1).
     *
     * @return array<string, string>
     */
    protected function seedTypeMap(ReflectionClass $reflection): array
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
     * Builds the {@see TypeSubstitutor} for `$reflection`, mapping the `self` /
     * `static` / `parent` keywords (plus the seeded concrete FQCNs) to their derived
     * interface FQCNs (A1).
     */
    protected function createTypeSubstitutor(ReflectionClass $reflection): TypeSubstitutor
    {
        $selfFqcn = $this->config->substituteSelfStatic ? Naming::derivedInterfaceFqcn($reflection) : null;
        $parent = $reflection->getParentClass();
        $parentFqcn = ($this->config->substituteSelfStatic && $parent !== false)
            ? Naming::derivedInterfaceFqcn($parent)
            : null;

        return new TypeSubstitutor($this->seedTypeMap($reflection), $selfFqcn, $parentFqcn);
    }
}
