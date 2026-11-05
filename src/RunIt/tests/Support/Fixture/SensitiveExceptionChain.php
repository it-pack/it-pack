<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Domain\Shared\ThrowableDescription;

/**
 * Builds nested throwable descriptions whose messages and traces carry
 * classified values (passwords, API keys, tokens) at multiple depths.
 *
 * Used by redaction and publication tests to assert that sensitive data in
 * nested previous causes and traces is masked, not just the top-level message.
 *
 * @see https://w3id.org/itpack/runit#ThrowableDescription
 */
final class SensitiveExceptionChain
{
    private const string API_KEY = 'sk-live-5f4dcc3b5aa765d61d8327deb882cf99';
    private const string DB_PASSWORD = 's3cret-p@ssw0rd';
    private const string SESSION_TOKEN = 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0In0.sig';

    /**
     * Builds a three-level chain: outer (db password) → middle (api key) → inner (session token).
     */
    public static function classifiedChain(): ThrowableDescription
    {
        $inner = ThrowableDescription::create(
            'RuntimeException',
            'session token leaked: ' . self::SESSION_TOKEN,
            0,
            null,
            'trace line 3: ' . self::SESSION_TOKEN,
        );

        $middle = ThrowableDescription::create(
            'PDOException',
            'api key leaked: ' . self::API_KEY,
            1,
            $inner,
            'trace line 2: ' . self::API_KEY,
        );

        return ThrowableDescription::create(
            'RuntimeException',
            'db password leaked: ' . self::DB_PASSWORD,
            2,
            $middle,
            'trace line 1: ' . self::DB_PASSWORD,
        );
    }

    /**
     * Returns the sensitive values embedded in the chain, for assertions.
     *
     * @return list<string>
     */
    public static function secrets(): array
    {
        return [self::API_KEY, self::DB_PASSWORD, self::SESSION_TOKEN];
    }
}
