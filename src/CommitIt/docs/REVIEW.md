# CommitIt — Architecture & Code Review

- **Date:** 2026-08-22
- **Scope:** `src/CommitIt` (CommitIt `1.0.0`), full source tree + `tests/` + QA config.
- **Method:** Manual source review of every PHP file in `src/`, cross-checked against `tests/Functional/GitPhpSecurityTest.php`, `composer.json`, `deptrac.yaml`, `infection.json5`, and the repository's `AGENTS.md` / `README.md`. QA gates (PHPStan `level: max`, DepTrac, PHP-CS-Fixer, Infection, `composer audit`) were already green at review time.
- **Author:** In-depth review (autonomous pass).

## Assumptions

- The review targets the code **as committed**; no runtime profiling was performed (no hot path warranted it). Performance claims are derived from static structure, not benchmarks.
- "Every class fully backed by interfaces" is read as a **hexagonal-boundary concern**: ports and public seams should be interface-backed; internal pure collaborators (value objects, validators, option builders) are judged on a YAGNI basis, not a blanket rule. Where the strict reading of `AGENTS.md` ("every concrete class must implement an appropriate interface") conflicts with DRY, both views are reported.
- Severity scale: **High** = correctness/security/contract break; **Medium** = design/security-robustness/maintainability; **Low** = style/cosmetic/optional.
- References use `file:line` against the reviewed tree.

---

## 1. Executive Summary

CommitIt is a **well-factored, genuinely hexagonal** library. Dependency direction is enforced and green (DepTrac: 0 violations), the domain is pure and adapter-free, value objects are immutable, and the observer is a clean non-intrusive decorator. Control-flow discipline is excellent: **no `else`/`elseif` exist anywhere in `src/`**, validation uses guard-return style, and literals that matter are named constants or centralized.

The gaps are concentrated in three themes:

1. **Interface Segregation (ISP).** The `Repository` port bundles ~25 methods into one god-port, contradicting `AGENTS.md`'s own "keep interfaces small and focused" rule and making safe extension hard (adding an operation is a breaking interface change).
2. **Observation completeness & path-scope robustness.** `ObservedRepository::getWorkingTree()` returns the inner tree **unwrapped**, so working-tree operations silently bypass observation; and `GitPhpWorkingTree::assertWithinRepositoryScope()` uses a bare `str_starts_with` that is defeated by a sibling directory whose name merely *prefixes* the repo parent (a real path-scope escape).
3. **Exception taxonomy & backend-coupling smells.** Validation exceptions extend `InvalidArgumentException` yet implement markers that sit under `VcsOperationException` (a `RuntimeException`); exception mapping is driven by English substring matching of upstream messages (locale-fragile), and `getLastCommit()` relies on a `'log'` substring heuristic.

None of these are release-blockers, but (2) is a security requirement per `AGENTS.md` and should be fixed before `1.0.0`.

---

## 2. Checklist Coverage

| # | Review dimension | Verdict |
|---|---|---|
| 1 | Domain purity & hexagonal boundaries | **Strong** — DepTrac green; Domain depends only on Common/Contract; no adapter leaks. |
| 2 | Ports & adapters / factories / interface backing | **Mostly strong** — ports present; factories inject `IRunner`; ISP and full interface-backing need work. |
| 3 | Modern PHP leverage | **Good** — `readonly`, property hooks (`RepositoryPath::realPath`), enums used; LazyObject/WeakMap unused (not yet needed). |
| 4 | Control-flow & literal hygiene | **Strong** — zero `else`/`elseif`; validators guard-return; key literals are constants/centralized. A few magic offsets/strings remain in the adapter. |
| 5 | Security surface | **Good, with one real gap** — validation + no-shell argv invocation are solid; worktree scope check has a prefix bug; `stage`/`remove` rely on git (not CommitIt) for scope. |
| 6 | DX, DRY & maintainability | **Strong** — clear `@api` docs, consistent naming, thin SDK; minor `run`/`runVoid` and adapter `run` duplication. |
| 7 | Performance & extension posture | **Good** — shared `Git` runner, lazy `realPath`, no per-call init; monitorable; no async need (fibers N/A). |

---

## 3. Strengths (keep doing these)

- **Dependency direction is real, not decorative.** `deptrac.yaml` maps Common → Contract → Domain → Application → SDK → Adapter, and the build enforces it with **0 violations / 0 skips**. This is the single most important architectural property and it holds.
- **Domain is pure and validation-first.** `RefNameValidator` and `CommitMessageValidator` are static, dependency-free, and run *before* the backend is touched (`BranchName::from()` → `RefNameValidator::assertValid()`). Value objects are `final` + `readonly` + named constructors (`RepositoryPath::from`, `CommitMessage::fromParts`, `CommitId::from`).
- **Observer is correctly decoupled.** `ObservedRepository` (`src/Application/ObservedRepository.php:33`) is a decorator implementing `Repository`, never alters the result, rethrows the original `Throwable`, and swallows observer failures on a separate path (`notify()`, `:237`) so the observer can neither re-observe nor mask real failures. `RepositoryOpener` (`src/Application/RepositoryOpener.php:25`) only wraps when a *real* observer is present, giving zero observation overhead with the `NullRepositoryObserver` default.
- **No `else`/`elseif` in `src/`** (verified by grep). Guard-return validation (`RefNameValidator::reason`, `:23`) keeps cyclomatic complexity low and intent obvious.
- **Factories inject the runner.** `GitPhpRepositoryFactory::__construct(?IRunner $runner)` (`src/Adapter/Git/GitPhpRepositoryFactory.php:31`) lets tests use `MemoryRunner` and keeps process execution behind a port.
- **Security thinking is present.** `GitPhpSecurityTest` documents *why* shell metacharacters are not in the rejection list (git-php uses argv, no shell), and covers path-escape + non-empty-dir overwrite. `ObservedRepository` deliberately omits commit bodies from observer context.
- **QA toolchain is mature.** PHPStan `level: max`, DepTrac, PHP-CS-Fixer (`@PHP84Migration` + `declare_strict_types`), Infection (with enforced MSI thresholds), and `composer audit` are all wired and green.

---

## 4. Findings

### F1 — `Repository` port violates ISP (Medium, architecture)
- **Evidence:** `src/Contract/Repository.php:26` exposes ~25 methods (branches, tags, remotes, commits, working tree, generic `execute`).
- **Problem:** Consumers needing only, say, branch operations must still depend on the entire surface. Per `AGENTS.md` ("Keep interfaces small and focused") and ISP, this is the opposite of the stated standard. It also makes **safe extension impossible**: adding `stash()`/`squash()` requires appending to `Repository`, breaking every third-party implementation — exactly the backward-compat trap `AGENTS.md` warns about ("Do not casually add methods to public interfaces").
- **Recommendation:** Decompose into focused ports — e.g. `CommitPort`, `BranchPort`, `TagPort`, `RemotePort`, `WorkingTreePort` — and have `GitPhpRepository` implement all of them, with `Repository` optionally extending the union for convenience. New capabilities arrive as new ports composed into the API, not as edits to a god-interface.

### F2 — `ObservedRepository::getWorkingTree()` is not observed (Medium, correctness/observability)
- **Evidence:** `src/Application/ObservedRepository.php:168` returns `$this->inner->getWorkingTree()` directly; `WorkingTree` operations (`listWorktrees`/`add`/`remove`) are never wrapped.
- **Problem:** Working-tree operations bypass the observer entirely, contradicting the decorator's stated guarantee ("dispatches lifecycle events around *every* repository operation"). A `RecordingObserver` would miss worktree activity.
- **Recommendation:** Introduce an `ObservedWorkingTree` decorator (same pattern as `ObservedRepository`) and return it from `getWorkingTree()`. Alternatively, document the limitation explicitly — but the former is preferred for consistency.

### F3 — Worktree path-scope check is bypassable via prefix confusion (Medium, security)
- **Evidence:** `src/Adapter/Git/GitPhpWorkingTree.php:139`:
  ```php
  if ($realRepoParent === false || !str_starts_with($realTargetParent, $realRepoParent)) {
  ```
- **Problem:** `str_starts_with('/srv2', '/srv')` is `true`. A repo at `/srv/repo` (parent `/srv`) would accept a worktree under `/srv2/wt` (parent `/srv2`) — escaping the intended scope. This directly violates the `AGENTS.md` security requirement that "a worktree path must never escape the repository's parent scope."
- **Recommendation:** Compare with a path separator so only true ancestry matches:
  ```php
  $inside = $realTargetParent === $realRepoParent
      || str_starts_with($realTargetParent . DIRECTORY_SEPARATOR, $realRepoParent . DIRECTORY_SEPARATOR);
  if ($realRepoParent === false || !$inside) {
  ```
- **Note:** The existing security test (`testWorktreeAddRejectsEscapingPath`) uses `/etc`, which is correctly rejected, so the bug is not caught by current tests. Add a sibling-prefix case (`/repo-parent-sibling/...`) to `GitPhpSecurityTest`.

### F4 — `stage`/`stageAll`/remove do not enforce path scope (Medium, security)
- **Evidence:** `GitPhpRepository::stage()` (`src/Adapter/Git/GitPhpRepository.php:44`) forwards the raw `string $file` straight to `addFile()` with no scope validation. `GitPhpSecurityTest::testStageRejectsEscapingPath` only passes **because git itself** rejects `../../`.
- **Problem:** `AGENTS.md` explicitly forbids "allow absolute paths to escape the repository scope for stage/remove." CommitIt currently delegates this guarantee to the backend rather than enforcing it. If a future backend (or a git version/config) is more permissive, the invariant breaks.
- **Recommendation:** Resolve and assert the staged path is within the repository working tree before invoking the backend, mirroring `assertWithinRepositoryScope()`. Add an adapter-level negative test that does not depend on git's own rejection (e.g. assert CommitIt throws `InvalidArgumentException` for out-of-scope input regardless of backend behavior).

### F5 — Exception taxonomy mismatch: validation exceptions vs `VcsOperationException` marker (Medium, design)
- **Evidence:** Markers form `InvalidReferenceNameException extends VcsOperationException` (`src/Contract/Exception/InvalidReferenceNameException.php:13`), and `VcsOperationException` is a `RuntimeException` (`src/Domain/Exception/VcsOperationException.php:15`). Yet the concrete `InvalidReferenceNameException`/`InvalidCommitMessageException` extend `InvalidArgumentException` (`src/Domain/Exception/InvalidReferenceNameException.php:16`, `InvalidCommitMessageException.php:15`).
- **Problem:** The marker hierarchy says validation failures *are* `VcsOperationException` (runtime, backend) failures, but the concrete classes are `LogicException`s thrown *before* any backend call. A consumer doing `catch (VcsOperationException $e)` will unexpectedly catch pre-backend validation errors; a consumer doing `catch (RuntimeException $e)` will *not* catch them. The semantics are inconsistent.
- **Recommendation:** Introduce a distinct `ValidationException` marker (extends `CommitItException`, not `VcsOperationException`) and have the validation exceptions implement it; reserve `VcsOperationException` for backend/runtime failures. This sharpens the taxonomy and aligns the class hierarchy with the marker hierarchy.

### F6 — `GitExceptionMap` is locale- and message-fragile (Medium, robustness)
- **Evidence:** `src/Adapter/Git/GitExceptionMap.php:67`–`104` classifies by English substrings (`'not found'`, `'unknown revision'`, `'working tree'`, …). `GitPhpRepository::getLastCommit()` (`:94`) uses `stripos($e->getMessage(), 'log') !== false`.
- **Problem:** If git runs under a non-English locale (`LC_ALL`/env), upstream messages are localized and the heuristics silently fall through to `VcsOperationException` (losing `RepositoryNotFoundException`/`DirtyWorkingTreeException` precision). The `'log'` heuristic depends on czproject echoing the command name in the error text.
- **Recommendation:** Force a stable locale in the runner environment (e.g. set `LC_ALL=C` / `LANG=C` on the git process) so message parsing is deterministic. Consider also classifying via git exit codes / `git rev-parse` pre-checks where feasible (e.g. determine "empty repo" with `rev-parse HEAD` instead of string-matching `getLastCommit`'s error).

### F7 — `getLastCommit()` "empty repo" detection is heuristic (Low, robustness)
- **Evidence:** `src/Adapter/Git/GitPhpRepository.php:89`–`99`. A `GitException` whose message contains `'log'` is treated as "no commits yet" and returns `null`; anything else is mapped.
- **Problem:** Relies on the command keyword appearing in the error text (see F6). Works today but is brittle.
- **Recommendation:** Probe the repo state explicitly (e.g. `git rev-parse --verify HEAD` wrapped in `run()`) and return `null` on "no commits," leaving genuine failures to `GitExceptionMap`.

### F8 — Duplicated `run`/`runVoid` helper bodies (Low, DRY)
- **Evidence:** `ObservedRepository::run()` (`:192`) and `runVoid()` (`:218`) are near-identical; the same `try/catch → GitExceptionMap::map` pattern is repeated in `GitPhpRepository::run()` (`:260`) and `GitPhpWorkingTree::run()` (`:151`).
- **Problem:** Pure duplication; a behavior change in dispatch/error handling must be made in 3–4 places.
- **Recommendation:** Keep `run`/`runVoid` as-is in `ObservedRepository` (the `void` split is a justified typing workaround, already documented), but extract the adapter-side `GitException → domain exception` wrapping into a single shared helper (a small `final class GitExceptionRunner` or a focused trait `MapsGitExceptions` used by both adapter classes).

### F9 — Magic offsets in `GitPhpWorkingTree::parseWorktreeList()` (Low, literal hygiene)
- **Evidence:** `src/Adapter/Git/GitPhpWorkingTree.php:95` `substr($line, 9)` and `:104` `substr($line, 7)` — the offsets are the lengths of the literals `'worktree '` (9) and `'branch '` (7).
- **Problem:** Numeric offsets are a form of magic number; they break silently if the literal changes.
- **Recommendation:** Derive the offset from the literal: `substr($line, strlen('worktree '))`. (The prefix check itself already uses `str_starts_with`, so only the offset needs this treatment.)

### F10 — `Commit` mapper null-handling is inconsistent (Low, latent bug)
- **Evidence:** `GitPhpRepository::mapCommit()` (`:269`) uses `?? ''` for author/committer *name* but assigns `getAuthorEmail()` / `getCommitterEmail()` directly into `string` properties (`:276`, `:279`).
- **Problem:** If czproject returns `null` for an email, a `TypeError` is thrown (the `Commit` constructor types both emails as `string`). The defensive `?? ''` applied to names is not applied to emails — inconsistent and a latent crash.
- **Recommendation:** Apply `?? ''` to emails as well, or assert non-null with a clear mapping exception.

### F11 — Stale documentation on `CommitId` (Low, maintainability)
- **Evidence:** `src/Domain/Value/CommitId.php:10`–`13` still says "Skeleton for Milestone M1 … Backend-specific format validation is enforced by the adapter and the dedicated validators in Milestone M2."
- **Problem:** Misleading; M1/M2 are done. Also note the domain never validates the 40-hex SHA-1 shape (left to the adapter per design) — fine, but the doc should reflect the final contract.
- **Recommendation:** Update the docblock to describe the *current* guarantee (opaque, non-empty, validated at the adapter boundary).

### F12 — `getWorkingTree()` rebuilds a wrapper each call (Low, performance)
- **Evidence:** `GitPhpRepository::getWorkingTree()` (`:245`) returns `new GitPhpWorkingTree($this->repository)` on every call.
- **Problem:** Minor allocation churn in loops; the wrapper is stateless but re-created each time.
- **Recommendation:** Lazy-initialize once (e.g. a `readonly`/cached property, or a `LazyObject`), or simply memoize. Low priority.

---

## 5. Interface-Backing Audit ("is every class backed by an interface?")

**Ports & public seams (good):** `Repository`, `WorkingTree`, `RepositoryFactory`, `RepositoryObserver` (interfaces) are implemented by `GitPhpRepository`, `GitPhpWorkingTree`, `GitPhpRepositoryFactory`, `ObservedRepository`, and `NullRepositoryObserver`. All domain exceptions implement their `Contract\Exception\*` marker interfaces. This is the part that matters for hexagonal substitution and it is healthy.

**Not interface-backed (acceptable, by design):**

| Class(es) | Backed? | Assessment |
|---|---|---|
| `Domain\Value\*` (8 value objects) | No | **Correct as-is.** DTOs/value objects should not be interface-backed; forcing it would be YAGNI and contradict "don't create interfaces solely to duplicate." |
| `Domain\Option\*` (3 builders) | No | **Correct as-is.** Immutable config builders; interface would add no semantic value. |
| `Domain\Validation\*` (2 validators) | No | **Acceptable.** Could be port-backed for swap-in strategies, but no current consumer needs it. Optional. |
| `Application\RepositoryOpener` | No | **Optional.** It is an `@api` seam; an interface would aid substitution/testing. Low priority. |
| `Adapter\Git\GitExceptionMap` | No | **Correct.** Marked `@internal`; not a public extension point. |
| `SDK\CommitIt` | No | **Acceptable.** Thin stateless facade; an interface is unnecessary. |

**Verdict:** The strict `AGENTS.md` line ("every concrete class must implement an appropriate interface") is **not fully met**, but the deviations are confined to internal pure collaborators where an interface would be gratuitous. The recommendation is to **relax the rule explicitly for value objects / option builders / internal mappers** (document this carve-out), while keeping ports, decorators, factories, and the public exceptions interface-backed — which they already are.

---

## 6. SOLID & DRY Assessment

- **SRP:** Strong. Each class has one job (validate / map / decorate / adapt). No god-objects.
- **OCP:** Strong for backends (new VCS = new adapter; domain untouched) and observers (new observer = new implementation). Weak for the `Repository` surface itself (F1).
- **LSP:** Strong. `ObservedRepository` is substitutable for any `Repository`; `GitPhpWorkingTree` for `WorkingTree`.
- **ISP:** **Weak** (F1) — the monolithic `Repository` port. Primary SOLID gap.
- **DIP:** Strong. High layers depend on `Contract` abstractions; `Application` depends on ports, never on `czproject`.
- **DRY:** Good overall; minor duplication in dispatch/error-wrapping helpers (F8) and the `listBranches`/`listTags` map logic. No copy-paste of business rules.

---

## 7. Modern PHP Feature Opportunities

- **Property hooks / virtual properties — already used well.** `RepositoryPath::$realPath` (`src/Domain/Value/RepositoryPath.php:53`) is a cached virtual property; this is the right pattern and should be the template for other derived projections (e.g. a `CommitMessage::$hasBody` hook).
- **`readonly` — consistently applied** to value objects and option builders. `RepositoryPath` correctly keeps one mutable cache field (`resolvedRealPath`) outside the readonlyPromoted constructor — appropriate.
- **Enums — used appropriately.** `RefKind` (`:11`) is a closed `enum`, correctly chosen over a stringly-typed kind.
- **Lazy objects — opportunity (F12).** `GitPhpWorkingTree` and the observer wiring are candidates for `LazyObject` to defer construction of rarely-used or heavy collaborators. Not urgent.
- **WeakMap / WeakReference — not needed yet.** A plausible future use: memoize parsed `WorktreeInfo`/commit metadata keyed by the underlying `GitRepository` without owning it. Currently unnecessary; do not add prematurely.
- **Fibers — not applicable.** CommitIt is a synchronous wrapper over a CLI git; there is no concurrency model to benefit from fibers. Adding them would be speculative.
- **Traits — none used in `src/`.** Fine. If F8's shared error-wrapper is extracted, prefer a small `final` helper class over a trait to avoid hidden-dependency traits (per `AGENTS.md`).

---

## 8. Security Recap

- **Strengths:** Ref/commit-message validation rejects injection-shaped input at the boundary; git is invoked via argv (no shell), so metacharacters are not command-injection vectors (documented in `GitPhpSecurityTest.php:37`); observer context omits message bodies; clone/init refuse to overwrite non-empty dirs.
- **Gaps:** F3 (worktree prefix-escape — a real scope violation) and F4 (`stage`/remove delegate scope enforcement to the backend). F6 (locale-dependent mapping) is a robustness/observability risk rather than a direct exploit.
- **Recommendation:** Prioritize F3 + F4 before `1.0.0`; add negative tests that assert CommitIt-enforced rejection independent of git's own behavior.

---

## 9. Performance & DX Recap

- **Performance:** Good. Shared `Git` runner, lazy `realPath`, no per-operation adapter construction, bounded parsing. No benchmarks needed; no hot-path concern identified.
- **DX:** Strong. `@api` annotations, consistent `ItPack\CommitIt\*` vocabulary (no git slang leaking into ports), thin `CommitIt` SDK facade, and a complete local QA gate set. The `getWorkingTree()` observation gap (F2) is a DX surprise worth closing.

---

## 10. Prioritized Recommendations

| Priority | Item | Finding |
|---|---|---|
| **P0 (pre-1.0.0)** | Fix worktree scope prefix bug + add sibling-prefix test | F3 |
| **P0 (pre-1.0.0)** | Enforce stage/remove path scope in the adapter, not git | F4 |
| **P1** | Split `Repository` into focused ports (ISP) | F1 |
| **P1** | Observe `WorkingTree` (decorate `getWorkingTree()`) | F2 |
| **P1** | Separate `ValidationException` from `VcsOperationException` marker | F5 |
| **P2** | Pin git locale in runner; reduce message-string reliance | F6, F7 |
| **P2** | Extract shared GitException→domain wrapper | F8 |
| **P3** | `substr` offsets from literals; email `?? ''`; update `CommitId` doc; lazy `getWorkingTree` | F9, F10, F11, F12 |
| **P3** | Document interface-backing carve-out for value objects/options | §5 |

---

## 11. Conclusion & Remaining Risk

CommitIt is **architecturally sound and production-shaped**. The hexagonal boundary is real and enforced, the domain is clean and pure, and the QA discipline (max PHPStan, DepTrac, Infection, audit) is well above average for a library at this stage. The highest-value work before `1.0.0` is **closing the two security gaps (F3, F4)** and **resolving the ISP/observation-completeness items (F1, F2)**. Everything else is incremental hardening.

No release-blocking defects in core functionality were found; the exception taxonomy (F5) and locale coupling (F6) are correctness/robustness refinements rather than active bugs.
