<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Enterprise;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\Enterprise\ConfigMapSource;
use ItPack\ConfigureIt\Adapter\Enterprise\DockerSecretsSource;
use ItPack\ConfigureIt\Adapter\Enterprise\KubernetesSecretSource;
use ItPack\ConfigureIt\Adapter\Enterprise\VolumeReader;
use ItPack\ConfigureIt\Adapter\EnvLoader;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\Exception\ReadException;

class VolumeSourceTest extends Unit
{
    private string $dir;

    public function testConfigMapResolvesWithProvenance(): void
    {
        $snapshot = new ConfigurationBuilder()
            ->addReader(new VolumeReader())
            ->addSource(new ConfigMapSource($this->dir))
            ->addLoader(new EnvLoader())
            ->build()
            ->resolve()
        ;

        verify($snapshot->get('app_name'))->equals('demo');
        verify($snapshot->get('app_debug'))->equals('true');
        verify($snapshot->get('db_host'))->equals('db.internal');

        $provenance = $snapshot->provenance('db_host');
        verify($provenance?->sourceKind())->equals('configmap');
        verify(str_starts_with($provenance?->resourceUri() ?? '', 'configmap://'))->true();
        verify($provenance?->format()?->value)->equals('env');
    }

    public function testKubernetesSecretResolvesWithProvenance(): void
    {
        $snapshot = new ConfigurationBuilder()
            ->addReader(new VolumeReader())
            ->addSource(new KubernetesSecretSource($this->dir))
            ->addLoader(new EnvLoader())
            ->build()
            ->resolve()
        ;

        verify($snapshot->get('app_name'))->equals('demo');

        $provenance = $snapshot->provenance('app_name');
        verify($provenance?->sourceKind())->equals('k8s-secret');
        verify(str_starts_with($provenance?->resourceUri() ?? '', 'k8s-secret://'))->true();
    }

    public function testDockerSecretsResolvesWithProvenance(): void
    {
        $snapshot = new ConfigurationBuilder()
            ->addReader(new VolumeReader())
            ->addSource(new DockerSecretsSource($this->dir))
            ->addLoader(new EnvLoader())
            ->build()
            ->resolve()
        ;

        verify($snapshot->get('db_host'))->equals('db.internal');

        $provenance = $snapshot->provenance('db_host');
        verify($provenance?->sourceKind())->equals('docker-secret');
        verify(str_starts_with($provenance?->resourceUri() ?? '', 'docker-secret://'))->true();
    }

    public function testVolumeReaderThrowsWhenMountMissing(): void
    {
        $source = new ConfigMapSource('/nonexistent/volume/' . uniqid());

        $resolver = new ConfigurationBuilder()
            ->addReader(new VolumeReader())
            ->addSource($source)
            ->addLoader(new EnvLoader())
            ->build()
        ;

        $this->expectException(ReadException::class);

        $resolver->resolve();
    }

    protected function _before(): void
    {
        $this->dir = sys_get_temp_dir() . '/cfg-vol-' . uniqid();
        mkdir($this->dir);
        file_put_contents($this->dir . '/app_name', 'demo');
        file_put_contents($this->dir . '/app_debug', 'true');
        file_put_contents($this->dir . '/db_host', 'db.internal');
    }

    protected function _after(): void
    {
        foreach (array_diff(scandir($this->dir), ['.', '..']) as $item) {
            unlink($this->dir . '/' . $item);
        }

        rmdir($this->dir);
    }
}
