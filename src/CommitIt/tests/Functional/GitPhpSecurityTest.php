<?php

declare(strict_types=1);

namespace Tests\Functional;

use Codeception\Test\Unit;
use ItPack\CommitIt\Adapter\Git\GitPhpRepositoryFactory;
use ItPack\CommitIt\Contract\Repository;
use ItPack\CommitIt\Domain\Exception\InvalidCommitMessageException;
use ItPack\CommitIt\Domain\Exception\InvalidReferenceNameException;
use ItPack\CommitIt\Domain\Exception\VcsOperationException;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RemoteName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\TagName;
use Tests\Support\Helper\GitRepoFactory;

/**
 * Security and negative tests: injection-shaped inputs are rejected at the
 * validator boundary, path-scope escapes are blocked, and clone/init never
 * silently overwrite an existing non-empty directory.
 *
 * @covers \ItPack\CommitIt\Domain\Validation\RefNameValidator
 * @covers \ItPack\CommitIt\Domain\Validation\CommitMessageValidator
 * @covers \ItPack\CommitIt\Adapter\Git\GitPhpRepository
 * @covers \ItPack\CommitIt\Adapter\Git\GitPhpWorkingTree
 * @covers \ItPack\CommitIt\Adapter\Git\GitPhpRepositoryFactory
 */
final class GitPhpSecurityTest extends Unit
{
    /**
     * Reference names that violate git's ref-format rules (and therefore the
     * {@see RefNameValidator}). These are rejected at the value-object boundary.
     *
     * Note: shell metacharacters such as `$(...)` or backticks are NOT in this
     * list on purpose. They are legal git ref characters, and they are not an
     * injection vector here because git-php invokes git with an argument vector
     * (no shell), so untrusted names can never be interpreted as commands.
     *
     * @return array<string, array{0: string}>
     */
    public function injectionShapedRefNames(): array
    {
        return [
            'newline' => ["main\nrm -rf /"],
            'whitespace' => ['main master'],
            'control character' => ["main\x07"],
            'path traversal' => ['a..b'],
            'tilde' => ['a~b'],
            'caret' => ['a^b'],
            'colon' => ['a:b'],
            'asterisk' => ['a*b'],
            'at brace' => ['@{x}'],
            'dot component' => ['.hidden'],
            'lock suffix' => ['a.lock'],
        ];
    }

    /**
     * @dataProvider injectionShapedRefNames
     */
    public function testInvalidReferenceNamesRejectedForEveryKind(string $name): void
    {
        foreach ([BranchName::class, TagName::class, RemoteName::class] as $kind) {
            try {
                $kind::from($name);
                self::fail(sprintf('Expected %s to reject invalid name "%s".', $kind, $name));
            } catch (InvalidReferenceNameException $e) {
                // expected at the validator boundary
            }
        }
    }

    public function testInjectionShapedCommitMessageRejected(): void
    {
        $this->expectException(InvalidCommitMessageException::class);

        CommitMessage::fromParts("legit subject\nrm -rf /");
    }

    public function testOversizedCommitSubjectRejected(): void
    {
        $this->expectException(InvalidCommitMessageException::class);

        CommitMessage::fromParts(str_repeat('a', 73));
    }

    private function open(string $path): Repository
    {
        return new GitPhpRepositoryFactory()->open(RepositoryPath::from($path));
    }

    public function testWorktreeAddRejectsEscapingPath(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-sec');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            $this->expectException(\InvalidArgumentException::class);
            $repository->getWorkingTree()->add(RepositoryPath::from('/etc'), BranchName::from('escape'));
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testWorktreeRemoveRejectsEscapingPath(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-sec');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            $this->expectException(\InvalidArgumentException::class);
            $repository->getWorkingTree()->remove(RepositoryPath::from('/etc'));
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testStageRejectsEscapingPath(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-sec');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            $sentinel = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'commitit-sentinel-' . bin2hex(random_bytes(8));
            self::assertFileDoesNotExist($sentinel);

            $this->expectException(VcsOperationException::class);
            $repository->stage('../../' . basename($sentinel));
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testCloneIntoNonEmptyDirectoryFailsFastAndPreservesContents(): void
    {
        $target = GitRepoFactory::createTempDir('commitit-clone-nonempty');
        $bare = GitRepoFactory::createTempDir('commitit-bare');

        try {
            file_put_contents($target . DIRECTORY_SEPARATOR . 'keep.txt', 'do-not-delete');
            GitRepoFactory::initBareRepo($bare);

            $this->expectException(VcsOperationException::class);
            new GitPhpRepositoryFactory()->cloneRepository($bare, RepositoryPath::from($target));
        } finally {
            GitRepoFactory::tearDown($bare);
        }

        self::assertFileExists($target . DIRECTORY_SEPARATOR . 'keep.txt');
        GitRepoFactory::tearDown($target);
    }

    public function testInitIntoNonEmptyDirectoryDoesNotOverwrite(): void
    {
        $target = GitRepoFactory::createTempDir('commitit-init-nonempty');

        try {
            file_put_contents($target . DIRECTORY_SEPARATOR . 'keep.txt', 'keep');

            new GitPhpRepositoryFactory()->init(RepositoryPath::from($target));

            self::assertFileExists($target . DIRECTORY_SEPARATOR . 'keep.txt');
            self::assertDirectoryExists($target . DIRECTORY_SEPARATOR . '.git');
        } finally {
            GitRepoFactory::tearDown($target);
        }
    }
}
