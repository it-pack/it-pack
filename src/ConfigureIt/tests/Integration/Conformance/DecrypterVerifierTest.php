<?php

declare(strict_types=1);

namespace Tests\Integration\Conformance;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\CommandRunner\NativeCommandRunner;
use ItPack\ConfigureIt\Adapter\Decryption\AgeDecrypter;
use ItPack\ConfigureIt\Adapter\Decryption\SopsDecrypter;
use ItPack\ConfigureIt\Contract\DecrypterInterface;
use ItPack\ConfigureIt\Exception\DecryptionException;

/**
 * Conformance verifier for the {@see DecrypterInterface} port.
 *
 * Every decrypter — the cloud/CLI adapters and any in-memory test double — must
 * fail safe: garbage ciphertext is rejected with a
 * {@see DecryptionException} rather than leaking partial cleartext. Binary
 * adapters (`age`, `sops`) are only included when their engine is installed;
 * the in-memory double keeps the contract exercised everywhere (RunIt-style
 * parameterized verifier).
 */
final class DecrypterVerifierTest extends Unit
{
    private function hasBinary(string $name): bool
    {
        $paths = explode(PATH_SEPARATOR, (string) getenv('PATH'));
        foreach ($paths as $dir) {
            if (is_file($dir . DIRECTORY_SEPARATOR . $name) && is_executable($dir . DIRECTORY_SEPARATOR . $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return iterable<string, array{string, DecrypterInterface}>
     */
    public function decrypters(): iterable
    {
        yield 'in-memory' => [
            'in-memory',
            new class () implements DecrypterInterface {
                public function decrypt(string $ciphertext): string
                {
                    if (str_starts_with($ciphertext, 'ENC:')) {
                        return substr($ciphertext, 4);
                    }

                    throw new DecryptionException('Invalid ciphertext.');
                }
            },
        ];

        if ($this->hasBinary('age')) {
            $key = sys_get_temp_dir() . '/cfg-age-' . uniqid() . '.key';
            yield 'age' => ['age', new AgeDecrypter(new NativeCommandRunner(), $key)];
        }

        if ($this->hasBinary('sops')) {
            yield 'sops' => ['sops', new SopsDecrypter(new NativeCommandRunner())];
        }
    }

    /**
     * @dataProvider decrypters
     */
    public function testFailSafeOnGarbageCiphertext(string $name, DecrypterInterface $decrypter): void
    {
        $this->expectException(DecryptionException::class);

        $decrypter->decrypt('@@this-is-not-valid-ciphertext@@');
    }
}
