<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\Naming\DictionaryNameResolver;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralRole;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralSubkind;
use ItPack\DisenchantIt\ValueObject\Literal\StringLiteral;

final class DictionaryNameResolverTest extends Unit
{
    private function literal(string $value): StringLiteral
    {
        return new StringLiteral(
            raw: $value,
            value: $value,
            line: 1,
            role: LiteralRole::Unclassified,
            subkind: LiteralSubkind::Unknown,
            className: 'C',
            methodName: 'f',
        );
    }

    public function testCreateAndResolveHit(): void
    {
        $resolver = DictionaryNameResolver::create(['admin' => 'ADMIN']);

        $this->assertSame('ADMIN', $resolver->resolve($this->literal('admin')));
    }

    public function testResolveMissReturnsNull(): void
    {
        $resolver = DictionaryNameResolver::create(['admin' => 'ADMIN']);

        $this->assertNull($resolver->resolve($this->literal('other')));
    }

    public function testFromFileHappyPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dict');
        file_put_contents($path, (string) json_encode(['admin' => 'ADMIN', 'guest' => 'GUEST']));

        try {
            $resolver = DictionaryNameResolver::fromFile($path);
            $this->assertSame('ADMIN', $resolver->resolve($this->literal('admin')));
            $this->assertSame('GUEST', $resolver->resolve($this->literal('guest')));
        } finally {
            unlink($path);
        }
    }

    public function testFromFileMissingThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        DictionaryNameResolver::fromFile('/path/that/does/not/exist.json');
    }

    public function testFromFileRejectsNonObjectJson(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dict');
        file_put_contents($path, '"just a string"');

        try {
            $this->expectException(\RuntimeException::class);
            DictionaryNameResolver::fromFile($path);
        } finally {
            unlink($path);
        }
    }

    public function testFromFileArrayYieldsEmptyMap(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dict');
        file_put_contents($path, '[1, 2, 3]');

        try {
            $resolver = DictionaryNameResolver::fromFile($path);
            $this->assertNull($resolver->resolve($this->literal('admin')));
        } finally {
            unlink($path);
        }
    }

    public function testFromFileIgnoresNonStringEntries(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dict');
        file_put_contents($path, (string) json_encode(['admin' => 'ADMIN', 'num' => 5]));

        try {
            $resolver = DictionaryNameResolver::fromFile($path);
            $this->assertSame('ADMIN', $resolver->resolve($this->literal('admin')));
            $this->assertNull($resolver->resolve($this->literal('num')));
        } finally {
            unlink($path);
        }
    }
}
