<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface;
use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSchemaInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\DeclaresScalarTypesInterface;
use ItPack\ConfigureIt\Execution\ExecutionContext;

/**
 * Computes a deterministic composite hash that identifies a resolution result.
 *
 * The hash folds in everything that can change the resolved snapshot: the
 * discovered source resources (path + modification time, so an edit invalidates
 * the cache), the schema identity + declared scalar types, the execution
 * context (environment + attributes), and the class of every processor / reader
 * / loader in the pipeline. Two builds that should resolve identically produce
 * the same key; anything that would change the output changes the key, which is
 * exactly the contract the {@see \ItPack\ConfigureIt\Cache\FileSystemCache}
 * relies on to serve or invalidate a compiled artifact.
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §7 "Compiled / Cached Artifacts"
 */
final class ConfigurationCompiler
{
    /**
     * @param iterable<ConfigurationSourceInterface> $sources
     * @param list<ConfigurationProcessorInterface>  $processors
     * @param list<ConfigurationReaderInterface>     $readers
     * @param list<ConfigurationLoaderInterface>     $loaders
     */
    public function hash(
        iterable                      $sources,
        ?ConfigurationSchemaInterface $schema,
        array                         $processors,
        array                         $readers,
        array                         $loaders,
        ?ExecutionContext             $context,
    ): string {
        $descriptors = [];

        foreach ($sources as $source) {
            foreach ($source->discover() as $resource) {
                $uri = $resource->uri();

                // Files are versioned by content hash so any edit invalidates
                // the cache deterministically (mtime alone is sub-second coarse);
                // non-file sources fall back to their uri as the version token.
                $hash = hash_file('sha256', $uri);
                $version = $hash !== false ? $hash : $uri;

                $descriptors[] = $uri . '#' . $version;
            }
        }

        sort($descriptors);

        $parts = [
            'sources'    => implode('|', $descriptors),
            'schema'     => $this->schemaFingerprint($schema),
            'context'    => $this->contextFingerprint($context),
            'processors' => implode(',', array_map('get_class', $processors)),
            'readers'    => implode(',', array_map('get_class', $readers)),
            'loaders'    => implode(',', array_map('get_class', $loaders)),
        ];

        return hash('sha256', serialize($parts));
    }

    private function schemaFingerprint(?ConfigurationSchemaInterface $schema): string
    {
        if ($schema === null) {
            return '';
        }

        $fingerprint = get_class($schema);

        if ($schema instanceof DeclaresScalarTypesInterface) {
            $fingerprint .= ':' . serialize($schema->scalarTypes());
        }

        return $fingerprint;
    }

    private function contextFingerprint(?ExecutionContext $context): string
    {
        if ($context === null) {
            return '';
        }

        return $context->environment()
            . ':' . json_encode($context->attributes(), JSON_THROW_ON_ERROR);
    }
}
