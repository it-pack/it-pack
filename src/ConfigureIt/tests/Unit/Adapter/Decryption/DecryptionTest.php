<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Decryption;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\CommandRunner\FakeCommandRunner;
use ItPack\ConfigureIt\Adapter\Decryption\AgeDecrypter;
use ItPack\ConfigureIt\Adapter\Decryption\DecryptingReader;
use ItPack\ConfigureIt\Adapter\Decryption\SopsDecrypter;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Contract\CommandRunnerInterface;
use ItPack\ConfigureIt\Contract\DecrypterInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\Exception\DecryptionException;
use ItPack\ConfigureIt\Exception\ReadException;
use ItPack\ConfigureIt\ValueObject\Format;

class DecryptionTest extends Unit
{
    private string $dir;

    protected function _before(): void
    {
        $this->dir = sys_get_temp_dir() . '/cfg-dec-' . uniqid();
        mkdir($this->dir);
    }

    protected function _after(): void
    {
        foreach (array_diff(scandir($this->dir), ['.', '..']) as $item) {
            unlink($this->dir . '/' . $item);
        }

        rmdir($this->dir);
    }

    public function testDecryptingReaderDecryptsEncAnnotatedResource(): void
    {
        $file = $this->dir . '/secret.json';
        file_put_contents($file, 'enc:ENCRYPTED-BLOB');

        $reader = new DecryptingReader(new FileReader(), self::fakeDecrypter('{"password":"decrypted"}'));

        verify($reader->read($this->resource($file)))->equals('{"password":"decrypted"}');
    }

    public function testDecryptingReaderPassesThroughUnannotatedResource(): void
    {
        $file = $this->dir . '/plain.json';
        file_put_contents($file, '{"password":"clear"}');

        $reader = new DecryptingReader(new FileReader(), self::throwingDecrypter());

        verify($reader->read($this->resource($file)))->equals('{"password":"clear"}');
    }

    public function testDecryptingReaderTransparentlyDecryptsSopsAnnotatedResource(): void
    {
        $file = $this->dir . '/secret.yaml';
        file_put_contents($file, "sops:\n  enc: BLOB");

        $reader = new DecryptingReader(new FileReader(), self::fakeDecrypter('decrypted-yaml'));

        verify($reader->read($this->resource($file)))->equals('decrypted-yaml');
    }

    public function testDecryptionFailureSurfacesAsReadException(): void
    {
        $file = $this->dir . '/bad.json';
        file_put_contents($file, 'enc:BLOB');

        $reader = new DecryptingReader(new FileReader(), self::throwingDecrypter());

        $this->expectException(ReadException::class);

        $reader->read($this->resource($file));
    }

    public function testAgeDecrypterUsesCommandRunner(): void
    {
        $decrypter = new AgeDecrypter(new FakeCommandRunner('age-plaintext'), '/keys/identity.key');

        verify($decrypter->decrypt('AGE-ENCRYPTED'))->equals('age-plaintext');
    }

    public function testSopsDecrypterUsesCommandRunner(): void
    {
        $decrypter = new SopsDecrypter(new FakeCommandRunner('sops-plaintext'));

        verify($decrypter->decrypt('SOPS-ENCRYPTED'))->equals('sops-plaintext');
    }

    public function testCommandRunnerFailureBecomesDecryptionException(): void
    {
        $runner = new class () implements CommandRunnerInterface {
            public function run(array $command, string $input = ''): string
            {
                throw new \RuntimeException('boom');
            }
        };

        $decrypter = new AgeDecrypter($runner, '/keys/identity.key');

        $this->expectException(DecryptionException::class);

        $decrypter->decrypt('x');
    }

    /**
     * @return DecrypterInterface
     */
    private static function fakeDecrypter(string $plaintext): DecrypterInterface
    {
        return new class ($plaintext) implements DecrypterInterface {
            public function __construct(private string $plaintext)
            {
            }

            public function decrypt(string $ciphertext): string
            {
                return $this->plaintext;
            }
        };
    }

    /**
     * @return DecrypterInterface
     */
    private static function throwingDecrypter(): DecrypterInterface
    {
        return new class () implements DecrypterInterface {
            public function decrypt(string $ciphertext): string
            {
                throw new \LogicException('should not be called');
            }
        };
    }

    private function resource(string $path): ConfigurationResourceInterface
    {
        return new class ($path) implements ConfigurationResourceInterface {
            public function __construct(private string $path)
            {
            }

            public function uri(): string
            {
                return $this->path;
            }

            public function format(): Format
            {
                return Format::JSON;
            }
        };
    }
}
