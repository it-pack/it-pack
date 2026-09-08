<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Svn;

use Codeception\Test\Unit;
use ItPack\CommitIt\Adapter\Svn\Exception\SvnAdapterException;
use ItPack\CommitIt\Adapter\Svn\SvnPhpRepository;
use ItPack\CommitIt\Adapter\Svn\SvnPhpRepositoryFactory;
use ItPack\CommitIt\Domain\Exception\RepositoryNotFoundException;
use ItPack\CommitIt\Domain\Value\ProcessResult;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use Tests\Support\MemoryProcessRunner;

/**
 * @covers \ItPack\CommitIt\Adapter\Svn\SvnPhpRepositoryFactory
 */
final class SvnPhpRepositoryFactoryTest extends Unit
{
    private MemoryProcessRunner $runner;

    private SvnPhpRepositoryFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner = new MemoryProcessRunner();
        $this->factory = new SvnPhpRepositoryFactory($this->runner, 'testuser');
    }

    public function testOpenReturnsRepositoryWhenPathIsValid(): void
    {
        $this->runner->setResult(
            ['svn', 'info', '--show-item', 'relative-url'],
            new ProcessResult('trunk', '', 0),
        );

        $repo = $this->factory->open(RepositoryPath::from('/tmp/svn-test'));

        self::assertInstanceOf(SvnPhpRepository::class, $repo);
        self::assertSame('/tmp/svn-test', $repo->getPath()->toString());
    }

    public function testOpenThrowsWhenNotASvnWorkingCopy(): void
    {
        $this->runner->setResult(
            ['svn', 'info', '--show-item', 'relative-url'],
            new ProcessResult('', 'svn: E155310: The node "/tmp/not-svn" was not found.', 1),
        );

        $this->expectException(RepositoryNotFoundException::class);
        $this->factory->open(RepositoryPath::from('/tmp/not-svn'));
    }

    public function testInitThrows(): void
    {
        $this->expectException(SvnAdapterException::class);
        $this->factory->init(RepositoryPath::from('/tmp/svn-test'));
    }

    public function testCloneRepositoryReturnsRepository(): void
    {
        $this->runner->setResult(
            ['svn', 'checkout', '--non-interactive', '--username', 'testuser', 'http://svn.example.com/repo', '/tmp/svn-checkout'],
            new ProcessResult("Checked out revision 42.\n", '', 0),
        );

        $repo = $this->factory->cloneRepository(
            'http://svn.example.com/repo',
            RepositoryPath::from('/tmp/svn-checkout'),
        );

        self::assertInstanceOf(SvnPhpRepository::class, $repo);
    }
}
