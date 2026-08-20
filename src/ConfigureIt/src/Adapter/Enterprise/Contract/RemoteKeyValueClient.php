<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise\Contract;

/**
 * Fetches remote parameter-store / secret-manager data and returns a JSON body
 * ready for the {@see \ItPack\ConfigureIt\ValueObject\Format::JSON} loader.
 *
 * Implementations translate vendor APIs (AWS SSM, Azure App Config, GCP Secret
 * Manager) into a single nested JSON document. Tests inject a fake.
 */
interface RemoteKeyValueClient
{
    /**
     * @return string JSON-encoded configuration body.
     */
    public function fetch(string $uri): string;
}
