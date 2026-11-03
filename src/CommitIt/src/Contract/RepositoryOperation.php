<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

/**
 * Closed vocabulary of repository operations observed through
 * {@see RepositoryObserver}. Each case's `value` is the stable string emitted to
 * observers, so adding or renaming a case never changes what an existing observer
 * already receives.
 *
 * @api
 */
enum RepositoryOperation: string
{
    case GetPath = 'getPath';
    case HasChanges = 'hasChanges';
    case Stage = 'stage';
    case StageAll = 'stageAll';
    case Commit = 'commit';
    case GetLastCommit = 'getLastCommit';
    case GetCommit = 'getCommit';
    case CreateBranch = 'createBranch';
    case CheckoutBranch = 'checkoutBranch';
    case DeleteBranch = 'deleteBranch';
    case ListBranches = 'listBranches';
    case CreateTag = 'createTag';
    case DeleteTag = 'deleteTag';
    case ListTags = 'listTags';
    case AddRemote = 'addRemote';
    case RemoveRemote = 'removeRemote';
    case ListRemotes = 'listRemotes';
    case Fetch = 'fetch';
    case Pull = 'pull';
    case Push = 'push';
    case Execute = 'execute';
    case ListWorktrees = 'listWorktrees';
    case AddWorktree = 'add';
    case RemoveWorktree = 'remove';
    case SetSparsePatterns = 'setSparsePatterns';
    case GetSparsePatterns = 'getSparsePatterns';
    case DisableSparseCheckout = 'disableSparseCheckout';
    case FindOrphanedWorktrees = 'findOrphanedWorktrees';
    case CleanupOrphanedWorktrees = 'cleanupOrphanedWorktrees';
    case GetBranchInfo = 'getBranchInfo';
    case SearchBranches = 'searchBranches';
    case GetTagInfo = 'getTagInfo';
    case CleanupTags = 'cleanupTags';
    case ConfigGetAll = 'configGetAll';
    case ConfigGet = 'configGet';
    case ConfigSet = 'configSet';
    case ConfigUnset = 'configUnset';
}
