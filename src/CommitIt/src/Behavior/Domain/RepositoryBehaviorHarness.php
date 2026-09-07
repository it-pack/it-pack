<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Behavior\Domain;

use ItPack\CommitIt\Contract\Repository;
use ItPack\CommitIt\Domain\Option\BranchQuery;
use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\TagName;
use Random\RandomException;
use Throwable;

/**
 * Framework-neutral behavior harness that adapter authors run against their
 * {@see Repository} implementation to verify contract compliance.
 *
 * Usage:
 *   $result = RepositoryBehaviorHarness::verify($repo, $factory)->run();
 *   assert($result->passed(), implode("\n", iterator_to_array($result)));
 *
 * No test-framework base class is required: the harness returns a
 * {@see HarnessResult} collection of violations that any test framework
 * (or plain `assert`) can consume.
 *
 * @api
 */
final class RepositoryBehaviorHarness
{
    /** @var list<string> */
    private array $violations = [];

    private function __construct(
        private readonly Repository $repository,
    ) {
    }

    public static function verify(Repository $repository): self
    {
        return new self($repository);
    }

    public function run(): HarnessResult
    {
        $this->checkCommit();
        $this->checkBranchCreation();
        $this->checkBranchSearch();
        $this->checkTagCreation();
        $this->checkTagInfo();
        $this->checkWorkingTree();

        return new HarnessResult($this->violations);
    }

    private function checkCommit(): void
    {
        $this->check('commit() returns a valid CommitId', function (): bool {
            $message = CommitMessage::fromParts('conformance test');
            $id = $this->repository->commit($message, CommitOptions::default()->withAllowEmpty(true));

            return preg_match('/^[0-9a-f]{40}$/', $id->__toString()) === 1;
        });
    }

    /**
     * @throws RandomException
     */
    private function checkBranchCreation(): void
    {
        $name = BranchName::from('conformance-' . bin2hex(random_bytes(4)));

        $this->check('createBranch() then getBranchInfo() returns info', function () use ($name): bool {
            $this->repository->createBranch($name);

            return $this->repository->getBranchInfo($name) !== null;
        });
    }

    private function checkBranchSearch(): void
    {
        $this->check('searchBranches() returns a non-nullable list', function (): bool {
            return $this->repository->searchBranches(BranchQuery::all()) !== [];
        });
    }

    private function checkTagCreation(): void
    {
        $this->check('listTags() returns non-empty after createTag()', function (): bool {
            $name = TagName::from('conformance-' . bin2hex(random_bytes(4)));
            $this->repository->createTag($name);

            return count($this->repository->listTags()) > 0;
        });
    }

    /**
     * @throws RandomException
     */
    private function checkTagInfo(): void
    {
        $name = TagName::from('info-' . bin2hex(random_bytes(4)));
        $this->repository->createTag($name);

        $this->check('getTagInfo() returns info after createTag()', function () use ($name): bool {
            return $this->repository->getTagInfo($name) !== null;
        });
    }

    private function checkWorkingTree(): void
    {
        $this->check('getWorkingTree() returns a WorkingTree', function (): bool {
            $this->repository->getWorkingTree();
            return true;
        });
    }

    /**
     * @param callable(): bool $assertion
     */
    private function check(string $description, callable $assertion): void
    {
        try {
            $passed = $assertion();
        } catch (Throwable) {
            $passed = false;
        }

        if (!$passed) {
            $this->violations[] = $description;
        }
    }
}
