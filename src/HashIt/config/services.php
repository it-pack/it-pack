<?php

declare(strict_types=1);

namespace ItPack\HashIt\Config;

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\HasherInterface;
use ItPack\HashIt\HmacHasherInterface;
use ItPack\HashIt\KeyedHasherInterface;

/**
 * Framework-agnostic service wiring for the HashIt hashers.
 *
 * Returns a definition array suitable for PSR-11 / PHP-DI / Symfony DI:
 * each service id (the port interface FQCN) maps to a factory closure that
 * resolves the bound implementation via the composition root (NativeHashIt).
 *
 * This file contains NO hashing logic — it only exposes the existing hashers
 * as services. Consumers (bundles, containers) load this file and register the
 * returned array; no container is booted here.
 *
 * @return array<string, callable(): object>
 */
return [
    HasherInterface::class => static fn (): HasherInterface => NativeHashIt::create(),
    KeyedHasherInterface::class => static fn (): KeyedHasherInterface => NativeHashIt::keyedHasher(),
    HmacHasherInterface::class => static fn (): HmacHasherInterface => NativeHashIt::hmacHasher(),
];
