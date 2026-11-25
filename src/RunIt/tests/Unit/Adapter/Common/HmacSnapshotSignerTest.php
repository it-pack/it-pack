<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Common;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Common\HmacSnapshotSigner;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @group adapter
 * @group security
 */
/**
 * @group adapter
 * @group security
 */
#[CoversClass(HmacSnapshotSigner::class)]
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
