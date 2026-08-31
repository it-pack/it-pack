<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\HashIt\Adapter\NativeArgon2;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Contract\Argon2Interface;

class NativeArgon2Test extends Unit
{
    private function argon2(): Argon2Interface
    {
        return NativeHashIt::argon2();
    }

    public function testCompositionRootExposesArgon2(): void
    {
        self::assertInstanceOf(NativeArgon2::class, NativeHashIt::argon2());
        self::assertInstanceOf(Argon2Interface::class, NativeHashIt::argon2());
    }

    public function testHashIsArgon2IdAndVerifies(): void
    {
        $password = 'hunter2-but-stronger';

        $hash = $this->argon2()->hash($password);

        self::assertStringStartsWith('$argon2id$', $hash);
        self::assertTrue($this->argon2()->verify($password, $hash));
    }

    public function testWrongPasswordDoesNotVerify(): void
    {
        $hash = $this->argon2()->hash('correct-password');

        self::assertFalse($this->argon2()->verify('wrong-password', $hash));
    }

    public function testDistinctHashesForSamePasswordBothVerify(): void
    {
        $password = 'reused-password';

        $hashA = $this->argon2()->hash($password);
        $hashB = $this->argon2()->hash($password);

        self::assertNotSame($hashA, $hashB);
        self::assertTrue($this->argon2()->verify($password, $hashA));
        self::assertTrue($this->argon2()->verify($password, $hashB));
    }

    public function testCustomOptionsAreHonoured(): void
    {
        $hash = $this->argon2()->hash('pw', ['memory_cost' => 1024, 'time_cost' => 2, 'threads' => 1]);

        self::assertTrue($this->argon2()->verify('pw', $hash));
    }
}
