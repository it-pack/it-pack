<?php

declare(strict_types=1);

namespace Tests\Functional;

use Codeception\Test\Unit;
use ItPack\CommitIt\Adapter\Git\GitPhpRepositoryFactory;
use ItPack\CommitIt\Contract\Repository;
use ItPack\CommitIt\Domain\Option\BranchQuery;
use ItPack\CommitIt\Domain\Option\TagCleanupOptions;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\TagName;
use ItPack\CommitIt\Domain\Value\TagType;
use Tests\Support\Helper\GitRepoFactory;

/**
 * Functional coverage of branch search and tag lifecycle management.
 *
 * @covers \ItPack\CommitIt\Adapter\Git\GitPhpRepository
 * @covers \ItPack\CommitIt\Domain\Value\BranchInfo
 * @covers \ItPack\CommitIt\Domain\Value\TagInfo
 * @covers \ItPack\CommitIt\Domain\Value\TagType
 * @covers \ItPack\CommitIt\Domain\Option\BranchQuery
 * @covers \ItPack\CommitIt\Domain\Option\TagCleanupOptions
 */
final class GitPhpBranchTagFunctionalTest extends Unit
{
    private function open(string $path): Repository
    {
        return new GitPhpRepositoryFactory()->open(RepositoryPath::from($path));
    }

    public function testGetBranchInfoReturnsMetadata(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-branch');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            file_put_contents($repo . DIRECTORY_SEPARATOR . 'a.txt', 'content');
            $repository->stage('a.txt');
            $repository->commit(CommitMessage::fromParts('Initial commit'));

            $repository->createBranch(BranchName::from('feature-x'));
            $repository->checkoutBranch(BranchName::from('feature-x'));
            file_put_contents($repo . DIRECTORY_SEPARATOR . 'b.txt', 'feature');
            $repository->stage('b.txt');
            $repository->commit(CommitMessage::fromParts('Add b.txt'));

            $info = $repository->getBranchInfo(BranchName::from('feature-x'));

            self::assertNotNull($info);
            self::assertTrue($info->isCurrent);
            self::assertFalse($info->isRemote);
            self::assertNull($info->upstream);
            self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $info->commitId->__toString());
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testGetBranchInfoReturnsNullForMissingBranch(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-branch-null');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            file_put_contents($repo . DIRECTORY_SEPARATOR . 'a.txt', 'content');
            $repository->stage('a.txt');
            $repository->commit(CommitMessage::fromParts('Initial commit'));

            $info = $repository->getBranchInfo(BranchName::from('does-not-exist'));

            self::assertNull($info);
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testSearchBranchesReturnsAllWhenNoFilters(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-search');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            file_put_contents($repo . DIRECTORY_SEPARATOR . 'a.txt', 'content');
            $repository->stage('a.txt');
            $repository->commit(CommitMessage::fromParts('Initial commit'));

            $repository->createBranch(BranchName::from('feature-a'));
            $repository->createBranch(BranchName::from('feature-b'));

            $results = $repository->searchBranches(BranchQuery::all());

            self::assertCount(3, $results);
            $names = array_map(static fn ($info): string => $info->name->__toString(), $results);
            self::assertContains('main', $names);
            self::assertContains('feature-a', $names);
            self::assertContains('feature-b', $names);
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testSearchBranchesWithPattern(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-pattern');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            file_put_contents($repo . DIRECTORY_SEPARATOR . 'a.txt', 'content');
            $repository->stage('a.txt');
            $repository->commit(CommitMessage::fromParts('Initial commit'));

            $repository->createBranch(BranchName::from('feature-a'));
            $repository->createBranch(BranchName::from('feature-b'));
            $repository->createBranch(BranchName::from('hotfix'));

            $results = $repository->searchBranches(
                BranchQuery::all()->withPattern('feature-*'),
            );

            self::assertCount(2, $results);
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testSearchBranchesCurrentOnly(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-current');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            file_put_contents($repo . DIRECTORY_SEPARATOR . 'a.txt', 'content');
            $repository->stage('a.txt');
            $repository->commit(CommitMessage::fromParts('Initial commit'));

            $repository->createBranch(BranchName::from('feature-x'));
            $repository->checkoutBranch(BranchName::from('feature-x'));

            $results = $repository->searchBranches(
                BranchQuery::all()->currentOnly(),
            );

            self::assertCount(1, $results);
            self::assertSame('feature-x', $results[0]->name->__toString());
            self::assertTrue($results[0]->isCurrent);
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testGetTagInfoReturnsAnnotatedMetadata(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-tag-annotated');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            file_put_contents($repo . DIRECTORY_SEPARATOR . 'a.txt', 'content');
            $repository->stage('a.txt');
            $commitId = $repository->commit(CommitMessage::fromParts('Initial commit'));

            $repository->createTag(TagName::from('v1.0.0'), $commitId);

            $info = $repository->getTagInfo(TagName::from('v1.0.0'));

            self::assertNotNull($info);
            self::assertSame('v1.0.0', $info->name->__toString());
            self::assertSame($commitId->__toString(), $info->commitId->__toString());
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testGetTagInfoReturnsNullForMissingTag(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-tag-null');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            file_put_contents($repo . DIRECTORY_SEPARATOR . 'a.txt', 'content');
            $repository->stage('a.txt');
            $repository->commit(CommitMessage::fromParts('Initial commit'));

            $info = $repository->getTagInfo(TagName::from('nope'));

            self::assertNull($info);
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testCleanupTagsRemovesMatchingPattern(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-cleanup');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            file_put_contents($repo . DIRECTORY_SEPARATOR . 'a.txt', 'content');
            $repository->stage('a.txt');
            $commitId = $repository->commit(CommitMessage::fromParts('Initial commit'));

            $repository->createTag(TagName::from('v1.0.0'), $commitId);
            $repository->createTag(TagName::from('v2.0.0'), $commitId);
            $repository->createTag(TagName::from('experimental-tag'), $commitId);

            $removed = $repository->cleanupTags(
                TagCleanupOptions::default()->matchingPattern('experimental-*'),
            );

            self::assertCount(1, $removed);
            self::assertSame('experimental-tag', $removed[0]->__toString());

            $remaining = $repository->listTags();
            self::assertCount(2, $remaining);
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testCleanupTagsNoMatch(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-cleanup-nomatch');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            file_put_contents($repo . DIRECTORY_SEPARATOR . 'a.txt', 'content');
            $repository->stage('a.txt');
            $commitId = $repository->commit(CommitMessage::fromParts('Initial commit'));

            $repository->createTag(TagName::from('v1.0.0'), $commitId);

            $removed = $repository->cleanupTags(
                TagCleanupOptions::default()->matchingPattern('nonexistent-*'),
            );

            self::assertCount(0, $removed);

            $remaining = $repository->listTags();
            self::assertCount(1, $remaining);
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testLightweightTagHasCorrectType(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-lightweight');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            file_put_contents($repo . DIRECTORY_SEPARATOR . 'a.txt', 'content');
            $repository->stage('a.txt');
            $commitId = $repository->commit(CommitMessage::fromParts('Initial commit'));

            $repository->createTag(TagName::from('v1.0.0-light'), $commitId);

            $info = $repository->getTagInfo(TagName::from('v1.0.0-light'));

            self::assertNotNull($info);
            self::assertSame(TagType::Lightweight, $info->type);
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }
}
