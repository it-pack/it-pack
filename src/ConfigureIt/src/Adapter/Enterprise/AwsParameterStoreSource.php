<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise;

use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\ValueObject\ConfigurationResourceFactory;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Exposes an AWS Systems Manager Parameter Store path as a single JSON resource.
 *
 * The resource URI encodes the parameter path, e.g. `aws-ssm:///app` resolves the
 * SSM path `/app` recursively. The companion {@see \ItPack\ConfigureIt\Adapter\Enterprise\Client\AwsSsmClient}
 * (wired through {@see RemoteJsonReader}) fetches and nests the parameters.
 *
 * Discovery only — never reads or parses. Implements the `ConfigurationSource`
 * port. See ROADMAP Phase 8.
 */
final readonly class AwsParameterStoreSource implements ConfigurationSourceInterface
{
    public function __construct(
        private string                       $path = '/',
        private ConfigurationResourceFactory $resourceFactory = new ConfigurationResourceFactory(),
    ) {
    }

    /**
     * @return iterable<ConfigurationResourceInterface>
     */
    public function discover(): iterable
    {
        yield $this->resourceFactory->build('aws-ssm://' . $this->path, Format::JSON);
    }
}
