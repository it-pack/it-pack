<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface;
use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSchemaInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\DeclaresScalarTypesInterface;
use ItPack\ConfigureIt\Contract\Execution\ExecutionContextInterface;
use JsonException;

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
    private const string HASH_ALGO = 'sha256';

    /**
     * @param iterable<ConfigurationSourceInterface> $sources
     * @param list<ConfigurationProcessorInterface> $processors
     * @param list<ConfigurationReaderInterface> $readers
     * @param list<ConfigurationLoaderInterface> $loaders
     * @throws JsonException
     */
    public function hash(
        iterable                      $sources,
        ?ConfigurationSchemaInterface $schema,
        array                         $processors,
        array                         $readers,
        array                         $loaders,
        ?ExecutionContextInterface    $context,
    ): string {
        $descriptors = [];

        foreach ($sources as $source) {
            foreach ($source->discover() as $resource) {
                $uri = $resource->uri();

                // Files are versioned by content hash so any edit invalidates the
                // cache deterministically (mtime alone is sub-second coarse).
                // Non-file resources (https://, s3://, vault://, …) must NOT be
                // opened here: hash_file() would transparently fetch them through
                // a stream wrapper. Fall back to the uri as the version token.
                $version = $uri;

                if (is_file($uri)) {
                    $hashed = hash_file(self::HASH_ALGO, $uri);

                    if (false !== $hashed) {
                        $version = $hashed;
                    }
                }

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

        return hash(self::HASH_ALGO, serialize($parts));
    }

    private function schemaFingerprint(?ConfigurationSchemaInterface $schema): string
    {
        if (null === $schema) {
            return '';
        }

        $fingerprint = get_class($schema);

        if ($schema instanceof DeclaresScalarTypesInterface) {
            $fingerprint .= ':' . serialize($schema->scalarTypes());
        }

        return $fingerprint;
    }

    private function contextFingerprint(?ExecutionContextInterface $context): string
    {
        if (null === $context) {
            return '';
        }

        return $context->environment()
            . ':' . json_encode($context->attributes(), JSON_THROW_ON_ERROR);
    }
}
