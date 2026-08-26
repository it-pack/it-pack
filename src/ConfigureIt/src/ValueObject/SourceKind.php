<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\ValueObject;

/**
 * Closed vocabulary of where a resolved value originated.
 *
 * Replaces the previously raw `string $sourceKind` discriminator. The Core only
 * ever reasons about this enumeration; adapters mint the concrete kind from a
 * resource scheme or format. Serialization boundaries use {@see self::value} so
 * persisted artifacts stay string-compatible. Unknown kinds collapse to
 * {@see self::Other} rather than throwing, because provenance must never reject
 * a legitimate (even exotic) source.
 */
enum SourceKind: string
{
    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Other;
    }
    case File = 'file';
    case Env = 'env';
    case Programmatic = 'programmatic';
    case Default = 'default';
    case ConfigMap = 'configmap';
    case K8sSecret = 'k8s-secret';
    case DockerSecret = 'docker-secret';
    case AwsSsm = 'aws-ssm';
    case AzureAppConfig = 'azure-appconfig';
    case GcpSecretManager = 'gcp-secretmanager';
    case Vault = 'vault';
    case Http = 'http';
    case Consul = 'consul';
    case Etcd = 'etcd';
    case Memory = 'memory';
    case Other = '__other__';
}
