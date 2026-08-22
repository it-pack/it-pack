# CommitIt — Refactoring Plan

- **Source review:** [`docs/REVIEW.md`](docs/REVIEW.md) (findings F1–F12 + interface-backing audit §5).
- **Scope:** `src/CommitIt` (`1.0.0`). All changes are internal refactors; no change to the documented public *behavior* except where a new guarantee is explicitly added (F3/F4 scope enforcement).
- **How to use this document:** Each item is an executable unit with a Goal, Affected files, Steps, Tests, Verification, Dependencies, and Acceptance criteria. Work top-down by Priority (P0 → P3). Every item must keep the quality gates green before it is marked done.

## 0. Guardrails (must hold for every item)

These constraints come from `AGENTS.md` and the review; violating them silently re-introduces the problems we are fixing.

1. **Dependency direction is non-negotiable.** Keep `deptrac.yaml` at 0 violations / 0 skips. New ports go in `src/Contract/`; never let Domain depend on Adapter, Application depend on SDK, or core depend on `czproject/git-php`.
2. **Preserve the original `Throwable` as `previous`.** Anywhere a `GitException` is mapped, the new domain exception must carry it as `$previous` (already the convention in `GitExceptionMap`).
3. **No adapter code outside `src/Adapter/Git/`.** Scope checks, locale pinning, and exception mapping happen in the adapter; the domain stays pure.
4. **Public API changes require an ADR.** F1 (splitting `Repository`) and F5 (taxonomy change) alter public contracts — both get an ADR before code.
5. **Tests come with the change**, and negative/secure cases must not depend on git's own rejection (F3/F4/F6 note this explicitly).
6. **Verification per item:** `composer analyse`, `composer architecture`, `composer cs:check`, and the affected test subset must pass before moving on.

## 1. Execution order & milestones

| Milestone | Items | Why first |
|---|---|---|
| **M1 — Security closure (P0)** | F3, F4 | Block `1.0.0`; real path-scope escapes. Must ship before release. |
| **M2 — Contract hardening (P1)** | F1, F2, F5 (+ ADRs) | Reshape public surface; do before consumers depend on the current shape. |
| **M3 — Robustness (P2)** | F6, F7, F8 | Locale determinism + DRY of error wrapping. |
| **M4 — Polish (P3)** | F9, F10, F11, F12, §5 carve-out | Low-risk hygiene; can land any time. |

---

## F3 — Worktree path-scope check is bypassable (P0, security)

**Goal:** Make `assertWithinRepositoryScope()` match *true ancestry* only, so a sibling directory whose name merely prefixes the repo parent is rejected.

**Affected files:**
- `src/Adapter/Git/GitPhpWorkingTree.php` (`assertWithinRepositoryScope()`, ~line 124)
- `tests/Functional/GitPhpSecurityTest.php` (add sibling-prefix case)
- `tests/Unit/Adapter/Git/GitPhpWorkingTreeTest.php` (add unit case)

**Steps:**
1. Replace the bare `str_starts_with($realTargetParent, $realRepoParent)` (line 139) with a separator-aware check:
   ```php
   $separator = DIRECTORY_SEPARATOR;
   $inside = $realTargetParent === $realRepoParent
       || str_starts_with($realTargetParent . $separator, $realRepoParent . $separator);
   if ($realRepoParent === false || !$inside) {
       throw new \InvalidArgumentException(
           'Worktree path must be located inside the repository parent directory.',
       );
   }
   ```
2. Keep the `realTargetParent === false` guard (non-existent parent) intact.

**Tests to add:**
- `tests/Functional/GitPhpSecurityTest.php`: `testWorktreeAddRejectsSiblingPrefixPath` — repo at `/tmp/<uniq>/repo-parent/repo`, attempt worktree under `/tmp/<uniq>/repo-parent-sibling/wt`; assert `InvalidArgumentException` thrown and **no** `git worktree add` executed (verify via `MemoryRunner`/`FunctionalTester`).
- `tests/Unit/Adapter/Git/GitPhpWorkingTreeTest.php`: same scenario against `MemoryRunner` so it does not depend on the filesystem.

**Verification:**
```bash
composer test:functional
composer test:unit
composer analyse
```
**Dependencies:** none.
**Acceptance:** Existing `/etc` escape test still passes; the new sibling-prefix case passes; `composer architecture` and `phpstan` clean.

---

## F4 — `stage` / `stageAll` / remove do not enforce path scope (P0, security)

**Goal:** Enforce that staged/removed paths resolve *inside* the repository working tree inside CommitIt, not via git's own rejection.

**Affected files:**
- `src/Adapter/Git/GitPhpRepository.php` (`stage()`, line 44; `stageAll()` is safe as it takes no path; `remove` does not currently exist on the port — only `stage` takes a path)
- `src/Adapter/Git/GitPhpWorkingTree.php` (reuse the scope helper pattern — consider a shared `FilePath` scope utility)
- `tests/Functional/GitPhpSecurityTest.php` (add adapter-level negative test)
- `tests/Unit/Adapter/Git/GitPhpRepositoryTest.php`

**Steps:**
1. Add a private `assertWithinWorkingTree(string $file)` to `GitPhpRepository` (or a small shared `final class PathScope` in `src/Adapter/Git/`) that:
   - Resolves `realpath` of the repo root (`$this->repository->getRepositoryPath()`) and the target file.
   - Applies the same separator-aware ancestry check as F3 (repo root, not repo *parent*, is the allowed scope for `stage`).
   - Throws `\InvalidArgumentException` when the resolved file is outside.
2. Call it at the top of `stage()` before `$this->repository->addFile($file)`.
3. Note: `stageAll()` and `remove` are out of scope for path validation (no caller-supplied path); leave them.

**Tests to add:**
- `tests/Functional/GitPhpSecurityTest.php`: `testStageRejectsEscapingPath` strengthened to assert CommitIt throws for `/abs/outside/repo` *independent* of git (the current test passes only because git rejects `../../` — add a case using an absolute path outside the repo that git would otherwise accept, e.g. staging a tracked-looking absolute path).
- `tests/Unit/Adapter/Git/GitPhpRepositoryTest.php`: stage-escape assertion via `MemoryRunner` (no git dependency).

**Verification:** `composer test`, `composer architecture`, `composer analyse`.
**Dependencies:** F3 (share the ancestry helper).
**Acceptance:** Staging an out-of-scope path throws before any backend call; in-scope staging unchanged; security test no longer relies on git's rejection.

---

## F1 — `Repository` port violates ISP (P1, architecture)

**Goal:** Decompose the ~25-method `Repository` god-port into focused ports so new capabilities arrive as new ports (composed), not as edits to a public interface.

**Affected files:**
- `src/Contract/Repository.php` (keep as convenience union interface extending the focused ports)
- New: `src/Contract/CommitPort.php`, `src/Contract/BranchPort.php`, `src/Contract/TagPort.php`, `src/Contract/RemotePort.php`, `src/Contract/WorkingTreePort.php`
- `src/Adapter/Git/GitPhpRepository.php` (implements the new ports)
- `src/Application/ObservedRepository.php` (implements the new ports; delegates)
- `src/Contract/WorkingTree.php` (already separate; keep)
- `docs/adr/ADR-006.md` (new ADR for the split)
- `deptrac.yaml` (no new layer; ports remain in `Contract` — verify no rule changes needed)

**Steps:**
1. **Write ADR-006** (`docs/adr/ADR-006.md`): decision to split `Repository` into `CommitPort`, `BranchPort`, `TagPort`, `RemotePort`; `Repository` becomes `interface Repository extends CommitPort, BranchPort, TagPort, RemotePort, WorkingTreePort` for backward convenience; `WorkingTreePort` is the existing `WorkingTree` interface. Record backward-compat impact (third-party `Repository` implementations must now also satisfy the sub-ports; because `Repository` extends them, a class implementing `Repository` is forced to implement all — acceptable, documented).
2. Create each sub-port with the methods currently in `Repository`:
   - `CommitPort`: `getPath, hasChanges, stage, stageAll, commit, getLastCommit, getCommit, execute`
   - `BranchPort`: `createBranch, checkoutBranch, deleteBranch, listBranches`
   - `TagPort`: `createTag, deleteTag, listTags`
   - `RemotePort`: `addRemote, removeRemote, listRemotes, fetch, pull, push`
   - `WorkingTreePort` = existing `WorkingTree` (already separate; refactor `Repository` to extend it).
3. Make `Repository` extend the union; `GitPhpRepository` and `ObservedRepository` `implements Repository` (implicitly satisfy sub-ports). No method-body changes required for the adapters.
4. Update `RepositoryOpener` / `CommitIt` facade only if they reference `Repository` type (they already do — no change).
5. Add port *contract tests* (see Tests) so future adapters implement the whole surface.

**Tests to add:**
- `tests/Unit/Contract/` port-conformance harness: `RepositoryPortContractTest` verifying a fake implementing `Repository` also satisfies `instanceof CommitPort` etc. Reuse `tests/Unit/Application/FakeRepository.php`.
- Keep `ObservedRepositoryTest` green; it already exercises every method — add an assertion that `ObservedRepository` is `instanceof` each new sub-port.

**Verification:** `composer architecture`, `composer analyse`, `composer test`.
**Dependencies:** none (do before F2 so `ObservedWorkingTree` lands on the split ports cleanly).
**Acceptance:** `deptrac` 0 violations; `GitPhpRepository` and `ObservedRepository` implement the sub-ports; `Repository` still usable everywhere; ADR-006 merged.

---

## F2 — `ObservedRepository::getWorkingTree()` is not observed (P1, observability)

**Goal:** Working-tree operations must dispatch observer events like every other operation.

**Affected files:**
- `src/Application/ObservedRepository.php` (`getWorkingTree()`, line 168)
- New: `src/Application/ObservedWorkingTree.php`
- `src/Contract/WorkingTree.php` (the port to decorate — see F1)
- `tests/Unit/Application/ObservedWorkingTreeTest.php` (new)
- `tests/Unit/Application/ObservedRepositoryTest.php` (extend: assert `getWorkingTree()` returns an `ObservedWorkingTree`)

**Steps:**
1. Create `final class ObservedWorkingTree implements WorkingTree` mirroring `ObservedRepository`'s decorator pattern: constructor takes `WorkingTree $inner` + `RepositoryObserver $observer`; wrap `listWorktrees`/`add`/`remove` with the same `run`/`runVoid`/`notify` helpers (extract those into a shared private helper or a tiny `final` trait-free helper class — see F8/AGENTS §modern PHP: prefer a small class over a trait).
2. In `ObservedRepository::getWorkingTree()` return `new ObservedWorkingTree($this->inner->getWorkingTree(), $this->observer)` instead of the raw inner tree (line 170).
3. Keep observer-context free of secrets (omit paths that may be sensitive? they are repo-relative identifiers — safe; do not include full commit bodies — N/A for worktree).

**Tests to add:**
- `ObservedWorkingTreeTest`: a `RecordingObserver` records `onStart`/`onSuccess` for `listWorktrees`, `add`, `remove`; `onFailure` when the inner throws.
- Extend `ObservedRepositoryTest`: `getWorkingTree()` returns an instance of `ObservedWorkingTree` and a worktree `add` is observed through it.

**Verification:** `composer test`, `composer analyse`.
**Dependencies:** F1 (so `WorkingTree` is the canonical port to decorate), F8 (shared notify helper).
**Acceptance:** All working-tree ops are observed; `NullRepositoryObserver` path unchanged; no double-observation of the wrapped `getWorkingTree()` call itself.

---

## F5 — Exception taxonomy mismatch (P1, design)

**Goal:** Separate *validation* failures (logic, pre-backend) from *VCS operation* failures (runtime, backend) in both the marker and concrete hierarchies.

**Affected files:**
- New: `src/Contract/Exception/ValidationException.php` (marker, `extends CommitItException`)
- `src/Contract/Exception/InvalidReferenceNameException.php` (change `extends VcsOperationException` → `extends ValidationException`)
- `src/Contract/Exception/InvalidCommitMessageException.php` (same change)
- `src/Domain/Exception/InvalidReferenceNameException.php` (already `extends InvalidArgumentException implements <Marker>` — keep; now marker is `ValidationException`)
- `src/Domain/Exception/InvalidCommitMessageException.php` (same)
- `docs/adr/ADR-007.md` (new ADR documenting the taxonomy change)
- Update any `catch (VcsOperationException $e)` in tests/docs that *should* now catch only runtime failures.

**Steps:**
1. **ADR-007**: decide that `ValidationException` sits under `CommitItException` (root marker, `src/Common/CommitItException`), parallel to `VcsOperationException`. Validation failures are `LogicException`-family; VCS-operation failures are `RuntimeException`-family.
2. Create `src/Contract/Exception/ValidationException.php`:
   ```php
   namespace ItPack\CommitIt\Contract\Exception;
   use ItPack\CommitIt\Common\CommitItException;
   interface ValidationException extends CommitItException {}
   ```
3. Repoint the two validation markers to `extends ValidationException`.
4. Confirm concrete classes remain `extends InvalidArgumentException implements <Marker>` — now consistent (marker = validation, concrete = logic exception).
5. Audit `tests/Unit/Domain/Exception/DomainExceptionTest.php` and `GitPhpExceptionMappingTest` for `catch (VcsOperationException)` that accidentally relied on catching validation exceptions; adjust expectations.

**Verification:** `composer analyse`, `composer test`, `composer architecture`.
**Dependencies:** none.
**Acceptance:** `catch (VcsOperationException $e)` no longer catches `InvalidReferenceNameException`/`InvalidCommitMessageException`; `catch (ValidationException $e)` does; PHPStan confirms marker hierarchy.

---

## F6 — `GitExceptionMap` is locale/message-fragile (P2, robustness)

**Goal:** Make backend error classification deterministic regardless of git's locale.

**Affected files:**
- `src/Adapter/Git/GitExceptionMap.php` (mapping heuristics)
- `src/Adapter/Git/GitPhpRepositoryFactory.php` (or the `IRunner` wiring) — pin locale
- `tests/Unit/Adapter/Git/GitExceptionMapTest.php` / `GitPhpExceptionMappingTest.php` (add locale-independence case)
- Possibly `src/Adapter/Git/` `IRunner` implementor (where the git process is spawned)

**Steps:**
1. Pin a stable locale for the git process: ensure the `IRunner`/command execution sets `LC_ALL=C` and `LANG=C` in the process environment. Confirm where the runner spawns git (factory or runner) and set it there, not per call.
2. Keep `GitExceptionMap`'s substring heuristics for *unknown* locales but document that under the pinned `C` locale they are stable.
3. (Complements F7) Reduce reliance on `'log'`/`'not found'` strings where an explicit git probe is feasible.

**Tests to add:**
- `GitExceptionMapTest`: build a `GitException` with a *non-English* message shape but the same underlying failure; assert mapping still resolves correctly under pinned locale (simulate by asserting the map function does not depend on message casing/locale once locale-pinned — at minimum add a regression test that the runner sets `LC_ALL=C`).

**Verification:** `composer test`, `composer analyse`.
**Dependencies:** F7 (shared "is-empty-repo" probe).
**Acceptance:** Git runs under `C` locale; mapping tests pass under a forced non-default host locale in CI.

---

## F7 — `getLastCommit()` empty-repo detection is heuristic (P2, robustness)

**Goal:** Detect "no commits yet" with an explicit probe instead of a `'log'` substring match.

**Affected files:**
- `src/Adapter/Git/GitPhpRepository.php` (`getLastCommit()`, line 87)
- `tests/Unit/Adapter/Git/GitPhpRepositoryTest.php` (extend with empty-repo + populated-repo)
- `tests/Functional/GitPhpFunctionalTest.php` (empty-repo scenario)

**Steps:**
1. In `getLastCommit()`, probe explicitly before reading:
   ```php
   try {
       $this->repository->execute('rev-parse', '--verify', 'HEAD');
   } catch (GitException) {
       return null; // no commits yet
   }
   try {
       $commit = $this->repository->getLastCommit();
   } catch (GitException $e) {
       throw GitExceptionMap::map($e, 'Failed to read last commit');
   }
   return self::mapCommit($commit);
   ```
2. Remove the `stripos($e->getMessage(), 'log')` branch.

**Tests to add:** empty-repo `getLastCommit()` returns `null` (via `MemoryRunner` and a real temp repo); populated repo returns a `Commit`.

**Verification:** `composer test`, `composer analyse`.
**Dependencies:** F6 (locale pin makes `rev-parse` error stable).
**Acceptance:** No substring matching for empty-repo; both states covered by tests.

---

## F8 — Duplicated `run` / `runVoid` error-wrapping (P2, DRY)

**Goal:** Single shared `GitException → domain exception` wrapper used by both adapters.

**Affected files:**
- `src/Adapter/Git/GitPhpRepository.php` (`run()`, line 260)
- `src/Adapter/Git/GitPhpWorkingTree.php` (`run()`, line 151)
- New: `src/Adapter/Git/GitExceptionRunner.php` (small `final` helper) — **not** a trait (per AGENTS.md)
- `tests/Unit/Adapter/Git/GitExceptionMapTest.php` (no behavior change, ensure coverage)

**Steps:**
1. Create `final class GitExceptionRunner` with:
   ```php
   public static function run(callable $operation, string $context): mixed
   {
       try {
           return $operation();
       } catch (GitException $e) {
           throw GitExceptionMap::map($e, $context);
       }
   }
   ```
   Use a `@template`/`@return` annotation for the generic.
2. Replace the inline `try/catch` in both adapters with `GitExceptionRunner::run(...)`.
3. Leave `ObservedRepository::run`/`runVoid` as-is (the `void` split is a justified typing workaround, documented there) — but have its body delegate to the same `GitExceptionMap::map` (already does).

**Verification:** `composer test`, `composer analyse`, `composer cs:check`.
**Dependencies:** none.
**Acceptance:** Both adapters delegate to `GitExceptionRunner`; `GitExceptionMap` coverage unchanged; no behavior change.

---

## F9 — Magic offsets in `parseWorktreeList()` (P3, literal hygiene)

**Goal:** Derive `substr` offsets from their literals.

**Affected files:** `src/Adapter/Git/GitPhpWorkingTree.php` (lines 95, 104)
**Steps:**
1. `substr($line, 9)` → `substr($line, strlen('worktree '))`.
2. `substr($line, 7)` → `substr($line, strlen('branch '))`.
**Tests:** none required (covered by existing `GitPhpWorkingTreeTest::parseWorktreeList`-style cases); add a unit on `parseWorktreeList` if not present.
**Verification:** `composer test`, `composer analyse`.
**Dependencies:** none.
**Acceptance:** Offsets are literal-derived; parsing output identical.

---

## F10 — `Commit` mapper null-handling inconsistency (P3, latent bug)

**Goal:** Apply the same null-safety to emails that names already have.

**Affected files:** `src/Adapter/Git/GitPhpRepository.php` (`mapCommit()`, line 269)
**Steps:**
1. Change `getAuthorEmail()` and `getCommitterEmail()` to `?? ''`:
   ```php
   $commit->getAuthorEmail() ?? '',
   ...
   $commit->getCommitterEmail() ?? '',
   ```
   (mirroring the `?? ''` already used for names at lines 275/278).
2. Alternatively, if czproject guarantees non-null, add a `CommitMappingException` — but `?? ''` matches existing style; prefer it.

**Tests to add:** `GitPhpRepositoryTest` mapping case with a commit whose email is null → assert `Commit` email is `''` (no `TypeError`).
**Verification:** `composer test`, `composer analyse`.
**Dependencies:** none.
**Acceptance:** Null email no longer throws; names/emails consistent.

---

## F11 — Stale `CommitId` docblock (P3, maintainability)

**Goal:** Reflect the final contract (opaque, non-empty, validated at adapter boundary).

**Affected files:** `src/Domain/Value/CommitId.php` (lines 7–13)
**Steps:**
1. Replace the "Skeleton for Milestone M1/M2" doc with:
   ```php
   /**
    * Validated, opaque commit identifier.
    *
    * The value is a non-empty opaque string. Its exact shape (git: 40-hex SHA-1)
    * is enforced at the adapter boundary, not in the domain; the domain only
    * guarantees non-emptiness and value equality.
    *
    * @api
    */
   ```
**Tests:** none (doc only).
**Verification:** `composer analyse` (docblocks don't change behavior; ensure no stray references).
**Dependencies:** none.
**Acceptance:** Doc matches current behavior; no "Milestone" references remain.

---

## F12 — `getWorkingTree()` rebuilds wrapper each call (P3, performance)

**Goal:** Memoize the `WorkingTree` wrapper.

**Affected files:** `src/Adapter/Git/GitPhpRepository.php` (`getWorkingTree()`, line 245)
**Steps:**
1. Cache the wrapper once:
   ```php
   private ?GitPhpWorkingTree $workingTree = null;

   public function getWorkingTree(): WorkingTree
   {
       return $this->workingTree ??= new GitPhpWorkingTree($this->repository);
   }
   ```
   The wrapper is stateless, so memoization is safe.
**Tests:** `GitPhpRepositoryTest` asserts repeated `getWorkingTree()` returns the same instance (or at least identical behavior).
**Verification:** `composer test`, `composer analyse`.
**Dependencies:** F2 (if `ObservedRepository` wraps it, the inner memoization is fine).
**Acceptance:** Single wrapper instance; no behavior change.

---

## §5 — Document interface-backing carve-out (P3, maintainability)

**Goal:** Make the "not every class needs an interface" decision explicit so future reviewers don't re-litigate it.

**Affected files:** `AGENTS.md` (add a short carve-out note) or `docs/REVIEW.md` §5 (already documents it — promote the carve-out to `AGENTS.md`).
**Steps:**
1. In `AGENTS.md` under "PHP Standards → Classes and contracts", add: "Value objects (`Domain\Value\*`), option builders (`Domain\Option\*`), and `@internal` mappers (`GitExceptionMap`) are intentionally **not** interface-backed; an interface there would be YAGNI. Ports, decorators, factories, and public exceptions remain interface-backed."
2. Keep the §5 table as the audit record.

**Tests:** none.
**Verification:** `composer quality` (no code change; doc only).
**Dependencies:** none.
**Acceptance:** Carve-out stated in `AGENTS.md`; matches REVIEW.md §5.

---

## 2. Cross-cutting verification matrix

Run after each milestone (M1–M4), not just at the end:

```bash
composer validate        # composer.json strict
composer cs:check        # PHP-CS-Fixer @PHP84Migration + declare_strict_types
composer analyse         # PHPStan level: max
composer architecture    # DepTrac: 0 violations / 0 skips
composer test            # Unit + Functional
composer mutation        # Infection MSI thresholds (run on M1/M2 at minimum)
composer security:audit  # composer audit
```

Or the bundled gate: `composer quality`.

## 3. Risk & rollback

- **F1/F5 are public-contract changes** → ship with ADRs (ADR-006, ADR-007). If a downstream consumer implements `Repository`, the ISP split forces them to satisfy sub-ports; this is acceptable pre-`1.0.0` and documented. After `1.0.0` these would be breaking — do them now.
- **F3/F4 change a security guarantee** → additive (tighter). No consumer relying on the loose behavior should exist; negative tests guard against regression.
- **Rollback unit** = one finding at a time, each behind its own commit + green gates, so any item can be reverted independently.

## 4. Definition of done

- [ ] All P0 (F3, F4) merged, with adapter-independent negative tests.
- [ ] All P1 (F1, F2, F5) merged, with ADRs and port/observer conformance tests.
- [ ] All P2 (F6, F7, F8) merged; locale pinned; empty-repo probe explicit; shared error wrapper.
- [ ] All P3 (F9, F10, F11, F12, §5) merged.
- [ ] `composer quality` green on the final tree.
- [ ] `docs/REVIEW.md` updated with a "Resolved" column referencing this plan and the merged PRs/commits.
