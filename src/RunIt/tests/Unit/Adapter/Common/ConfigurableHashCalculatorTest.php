<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Common;

use Codeception\Test\Unit;
use InvalidArgumentException;
use ItPack\RunIt\Adapter\Common\ConfigurableHashCalculator;
use ItPack\RunIt\Adapter\Common\HmacSnapshotSigner;

/**
 * @group adapter
 * @group security
 */
final class ConfigurableHashCalculatorTest extends Unit
{
    public function testDefaultAlgorithmIsSha256(): void
    {
        $calculator = new ConfigurableHashCalculator();

        self::assertSame(hash('sha256', 'payload'), $calculator->calculate('payload'));
    }

    public function testConfiguredAlgorithmIsUsed(): void
    {
        $calculator = new ConfigurableHashCalculator('sha1');

        self::assertSame(hash('sha1', 'payload'), $calculator->calculate('payload'));
    }

    public function testUnsupportedAlgorithmIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ConfigurableHashCalculator('nope');
    }
}

/**
 * @group adapter
 * @group security
 */
final class HmacSnapshotSignerTest extends Unit
{
    public function testSignatureVerifiesForSameContent(): void
    {
        $signer = new HmacSnapshotSigner('secret-key');

        $content = (string) json_encode(['status' => 'ok']);
        $signature = $signer->sign($content);

        self::assertTrue($signer->verify($content, $signature));
    }

    public function testTamperedContentFailsVerification(): void
    {
        $signer = new HmacSnapshotSigner('secret-key');

        $signature = $signer->sign('original');

        self::assertFalse($signer->verify('tampered', $signature));
    }

    public function testDifferentKeyFailsVerification(): void
    {
        $signer = new HmacSnapshotSigner('key-a');
        $other = new HmacSnapshotSigner('key-b');

        $content = 'content';
        $signature = $signer->sign($content);

        self::assertFalse($other->verify($content, $signature));
    }
}
