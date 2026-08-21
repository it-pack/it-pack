<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Codegen;

use Nette\Schema\Schema as NetteSchema;

/**
 * Generates strongly-typed, readonly DTO classes from a Nette Schema definition.
 *
 * This is the E2.3 companion capability: instead of consuming a snapshot through
 * `int()`/`string()` accessors, teams can generate an `AppConfig` DTO whose getters
 * are fully typed and hydrate it in one call. The generated code depends only on
 * {@see \ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface} and implements
 * {@see GeneratedConfigDtoInterface}.
 *
 * @see docs/architecture/MODEL.md "Typed Snapshot" (codegen companion, E2.3)
 */
final class ConfigCodegen
{
    public function __construct(
        private SchemaModelBuilder $builder = new SchemaModelBuilder(),
        private PhpRenderer        $renderer = new PhpRenderer(),
    ) {
    }

    /**
     * Produce PHP source for the root DTO plus any nested DTOs.
     *
     * @param string      $className FQCN-less class name (namespace added separately).
     * @param non-empty-string $namespace Target namespace for the generated classes.
     */
    public function generate(NetteSchema $schema, string $className, string $namespace = 'ItPack\\ConfigureIt\\Generated'): string
    {
        $models = $this->builder->build($schema, $className);

        return $this->renderer->render($models, $namespace);
    }
}
