# CommitIt Additional Features Plan

## Objective
Plan and reason on additional useful horizontal and vertical features to take CommitIt to the next level, building upon its current vendor-neutral VCS API and SDK foundation.

## Current State Summary
CommitIt is at version 1.0.0 scope with implemented core features:
- Git adapter (czproject/git-php)
- Repository/WorkingTree/Branch/Tag/Remote/Commit value objects
- RepositoryObserver for operation lifecycle tracking
- Hexagonal architecture with clear boundaries
- Observer-ready for future RunIt integration
- Modern PHP 8.4 with readonly classes and strict typing

The next evolutionary phase should focus on expanding functionality while maintaining vendor-neutrality and architectural integrity.

---

## Horizontal Features (Ecosystem-Level)

### 1. RunIt Observer Adapter Integration
**Priority**: HIGH

**Rationale**: CommitIt's `RepositoryObserver` port is explicitly designed to map to RunIt execution model, but the adapter is not yet built. This is the most natural ecosystem enhancement.

**Suggestions**:
- Build `it-pack/commit-it-runit` as the canonical adapter
- Map repository operations to RunIt's Activity/Measurement/Observation/Occurrence/Snapshot triad
- Define standardized occurrence names for all VCS operations
- Ensure observer failures are isolated and non-recursive
- Implement integration tests with real execution engine

**Architectural Alignment**: Maintains ports-not-imports, leverages existing `RepositoryObserver` contract.

### 2. Multi-VCS Adapter Framework
**Priority**: MEDIUM

**Rationale**: CommitIt's value proposition is vendor-neutral VCS access. Supporting only git limits third-party adoption.

**Suggestions**:
- Design common adapter interface pattern
- Implement placeholder adapters for common VCS systems:
  - SVN adapter (using phpseclib)
  - Mercurial adapter
  - Perforce adapter (if feasible)
- Create adapter conformance tests
- Document adapter implementation requirements
- Maintain `RepositoryObserver` mapping for each adapter

**Architectural Alignment**: Follows hexagonal adapter pattern, each VCS implementation isolated.

### 3. CI/CD Integration Patterns
**Priority**: MEDIUM

**Rationale**: CommitIt is used heavily in automation pipelines. Formalizing CI/CD integration patterns lowers adoption barriers.

**Suggestions**:
- Create `CommitItCi` package with CI-specific utilities:
  - Pre-commit hooks for commit message validation
  - CI status reporters (GitHub Actions, GitLab CI)
  - Branch protection checkers
  - Integration state persistence
- Document common CI/CD patterns (GitHub Actions examples, GitLab CI examples)
- Provide opinionated but configurable integration implementations

**Architectural Alignment**: Separate package, not coupled to core CommitIt.

### 4. Performance Optimizations
**Priority**: LOW

**Rationale**: CommitIt may face performance bottlenecks in high-volume scenarios.

**Suggestions**:
- Implement connection pooling for git operations
- Add lazy loading for commit metadata
- Optimize repository path resolution caching
- Add batch operations where possible (multiple file operations)
- Document performance characteristics and bottlenecks

**Architectural Alignment**: Performance enhancements without compromising architectural boundaries.

---

## Vertical Features (CommitIt-Specific)

### 1. Advanced Working Tree Operations
**Priority**: HIGH

**Rationale**: Working trees are a core feature but current implementation may lack advanced use cases.

**Suggestions**:
- **Sparse working trees**: Support for selective checkout of specific paths
- **Working tree permissions**: Attribute-based access control for worktrees
- **Worktree inheritance**: Patterns for worktree branching strategies
- **Worktree lifecycle**: Automatic cleanup of orphaned worktrees
- **Inter-worktree synchronization**: Detection and resolution of conflicts between worktrees
- **Worktree integration with branches**: Enhanced worktree management with branch relationships

**Architectural Alignment**: All within `WorkingTree` contract, no breaking changes.

### 2. Branch and Tag Management Enhancements
**Priority**: HIGH

**Rationale**: Branches and tags are fundamental to version control workflows.

**Suggestions**:
- **Branch protection rules**: Support for Git-style branch protection policies
- **Branch search indexing**: Enable efficient branch searching and filtering
- **Tag lifecycle management**: Automated tag cleanup, tag promotion patterns
- **Virtual branches**: Lightweight branching without full git operations (similar to GitHub Flow)
- **Branch-level permissions**: Per-branch access controls
- **Branch configuration**: Persistent branch settings (merge strategies, default behaviors)

**Architectural Alignment**: Extends `Branch` and `Tag` value objects without breaking changes.

### 3. Advanced Commit Handling
**Priority**: MEDIUM

**Rationale**: Commit operations are core but could be enhanced.

**Suggestions**:
- **Commit chaining**: Support for structured commit relationships (fixes, addresses, refs)
- **Merge automation**: Advanced merge strategies, automated conflict resolution
- **Cherry-picking**: With validation and conflict tracking
- **Commit graph analysis**: Tools for commit history analysis and visualization
- **Reverse commits**: Safe revert implementations
- **Commit payload storage**: Optional storage of additional metadata alongside commits

**Architectural Alignment**: All within existing `Commit` contract, adding computed properties and helper methods.

### 4. Repository Operations Enhancements
**Priority**: MEDIUM

**Rationale**: Repository operations could benefit from additional capabilities.

**Suggestions**:
- **Repository fingerprinting**: Generate hashes of repository state
- **Repository migration**: Tooling for migrating between VCS systems
- **Repository health checks**: Automated validation of repository integrity
- **Repository governance**: Tools for enforcing repository policies
- **Repository templates**: Standardized repository initialization patterns
- **Repository analytics**: Usage statistics and pattern analysis

**Architectural Alignment**: Extension of `Repository` contract with optional new operations.

### 5. Security and Safety Enhancements
**Priority**: HIGH

**Rationale**: Security is critical for version control systems.

**Suggestions**:
- **Advanced path validation**: Better detection of path traversal attempts
- **Content security scanning**: Detection of secrets, credentials in commits
- **Repository access control**: Fine-grained access permissions
- **Audit logging**: Comprehensive security event logging
- **Commit content validation**: Signatures, encryption, integrity checks
- **Hook system**: Pre/post-commit hooks with secure execution

**Architectural Alignment**: Security layers without compromising core invariants.

---

## Implementation Priorities

### Phase 1 (Next 6 months)
1. **RunIt Observer Adapter** - Critical for ecosystem integration
2. **Advanced Working Tree Operations** - High impact on existing workflows
3. **Branch/Tag Management Enhancements** - Fundamental VCS functionality
4. **Security Enhancements** - Non-negotiable for production use

### Phase 2 (6-12 months)
1. **Multi-VCS Adapter Framework** - Long-term vendor-neutrality
2. **Advanced Commit Handling** - Workflow improvements
3. **Repository Operations Enhancements** - Administrative capabilities
4. **CI/CD Integration Patterns** - Ecosystem tooling

### Phase 3 (12+ months)
1. **Performance Optimizations** - Scale and efficiency improvements
2. **Advanced Branching Strategies** - Professional workflow support

---

## Architectural Considerations

### 1. Maintain Vendor-Neutrality
All new features must remain framework-agnostic and not coupled to git-specific implementations.

### 2. Preserve Observable Behavior
The `RepositoryObserver` contract must continue to map correctly to any new execution model.

### 3. Backward Compatibility
New features should not break existing functionality or contracts.

### 4. Extension Over Modification
Prefer extending existing contracts rather than modifying core domain objects.

### 5. Testing Strategy
- New features require full test coverage
- Adapter implementations need both unit and integration tests
- Horizontal features require cross-package integration tests
- Performance-critical features require benchmarks

---

## Risk Mitigation

### 1. Scope Creep
- Implement features in phases with clear interfaces
- Use abstract base classes for planned functionality
- Keep concrete implementations minimal and focused

### 2. Architectural Drift
- Regular architecture reviews against deptrac rules
- Maintain clear separation between core and adapter features
- Keep `RepositoryObserver` mapping consistent across all adapters

### 3. Quality Assurance
- Each feature must pass `quality` gate (`cs:check`, `analyse`, `architecture`, `test`, `mutation`)
- Cross-feature integration testing
- Architecture tests updated to reflect new boundaries

### 4. Documentation
- Document all new public APIs
- Provide usage examples in `docs/USER_GUIDE.md`
- Update architectural decision records in `docs/adr/`

---

## Success Metrics

### Technical
- 100% test coverage for new features
- Zero architecture violations
- All quality gates passing
- Backward compatibility maintained (no breaking changes)

### Ecosystem
- RunIt adapter completed and tested
- Multi-VCS adapter framework established
- CI/CD integration patterns documented and tested
- At least 3 horizontal and 3 vertical features implemented

### Adoption
- New features used in existing examples
- Documentation examples updated
- README updated with new capabilities
- Community contributions encouraged via GitHub issues

---

## Next Steps

1. **Create feature specification documents** for each proposed feature
2. **Update `docs/IMPLEMENTATION_PLAN.md`** to reflect new roadmap
3. **Add architecture decision records** for significant design decisions
4. **Create test fixtures** for new functionality
5. **Update `AGENTS.md`** with guidelines for implementing these features
6. **Establish monitoring** for feature implementation progress

This plan provides a comprehensive roadmap for CommitIt's evolution while maintaining its core architectural principles and vendor-neutrality.