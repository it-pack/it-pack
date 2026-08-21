<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise;

use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\ValueObject\ConfigurationResourceFactory;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Exposes a single remote HTTP(S) JSON endpoint as a configuration resource.
 *
 * Discovery only — never reads or parses. The transport lives in
 * {@see HttpReader}, which performs the GET through the injected
 * {@see \ItPack\ConfigureIt\Adapter\Enterprise\Contract\HttpClient} (the
 * production {@see \ItPack\ConfigureIt\Adapter\Enterprise\Http\CurlHttpClient}
 * or a {@see \ItPack\ConfigureIt\Adapter\Enterprise\Http\FakeHttpClient} in
 * tests). Reuses the Phase 8 HTTP plumbing.
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §6 "Remote & Distributed Configuration"
 */
final readonly class HttpSource implements ConfigurationSourceInterface
{
    public function __construct(
        private string                                 $url,
        private Format                                 $format = Format::JSON,
        private ConfigurationResourceFactory $resourceFactory = new ConfigurationResourceFactory(),
    ) {
    }

    /**
     * @return iterable<ConfigurationResourceInterface>
     */
    public function discover(): iterable
    {
        yield $this->resourceFactory->build($this->url, $this->format);
    }
}
