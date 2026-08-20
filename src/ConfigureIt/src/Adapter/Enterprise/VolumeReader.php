<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise;

use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\Exception\ReadException;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Reads a Kubernetes/Docker volume mount directory into a flat "KEY=VALUE" body
 * for the {@see \ItPack\ConfigureIt\Adapter\EnvLoader}.
 *
 * Each file in the mount directory becomes one configuration key (the file name)
 * and its trimmed contents the value — the exact layout Kubernetes ConfigMaps,
 * Kubernetes Secrets and Docker Swarm secrets project into a pod. Knows how to
 * read the volume; never parses format. Implements the `ConfigurationReader`
 * port for the `configmap://`, `k8s-secret://` and `docker-secret://` schemes.
 *
 * @see docs/architecture/MODEL.md "Configuration Reader"
 */
final readonly class VolumeReader implements ConfigurationReaderInterface
{
    private const SCHEMES = ['configmap://', 'k8s-secret://', 'docker-secret://'];

    public function read(ConfigurationResourceInterface $resource): string
    {
        if (!$this->supports($resource)) {
            throw new ReadException(sprintf('VolumeReader cannot read resource "%s".', $resource->uri()));
        }

        $directory = $this->pathOf($resource->uri());

        if (!is_dir($directory)) {
            throw new ReadException(sprintf('Configuration volume not found: "%s".', $directory));
        }

        $lines = [];

        $entries = scandir($directory, SCANDIR_SORT_ASCENDING);
        $entries = is_array($entries) ? $entries : [];

        foreach ($entries as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $file = $directory . DIRECTORY_SEPARATOR . $name;

            if (!is_file($file)) {
                continue;
            }

            $content = (string) file_get_contents($file);
            $lines[] = $name . '=' . rtrim($content, "\r\n");
        }

        return implode("\n", $lines);
    }

    public function supports(ConfigurationResourceInterface $resource): bool
    {
        if ($resource->format() !== Format::ENV) {
            return false;
        }

        foreach (self::SCHEMES as $scheme) {
            if (str_starts_with($resource->uri(), $scheme)) {
                return true;
            }
        }

        return false;
    }

    private function pathOf(string $uri): string
    {
        foreach (self::SCHEMES as $scheme) {
            if (str_starts_with($uri, $scheme)) {
                return substr($uri, strlen($scheme));
            }
        }

        return $uri;
    }
}
