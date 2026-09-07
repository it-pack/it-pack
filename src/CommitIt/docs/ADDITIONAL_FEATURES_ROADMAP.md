# CommitIt Additional Features Roadmap

## Overview
This roadmap outlines the implementation of additional features for CommitIt (version 2.0.0+) as described in the ADDITIONAL_FEATURES_PLAN.md. The roadmap is structured around horizontal (ecosystem-level) and vertical (CommitIt-specific) features, organized into three implementation phases.

## Implementation Summary

### Horizontal Features (Ecosystem-Level)
- **RunIt Observer Adapter Integration** - HIGH priority
- **Multi-VCS Adapter Framework** - MEDIUM priority
- **CI/CD Integration Patterns** - MEDIUM priority
- **Performance Optimizations** - LOW priority

### Vertical Features (CommitIt-Specific)
- **Advanced Working Tree Operations** - HIGH priority
- **Branch and Tag Management Enhancements** - HIGH priority
- **Advanced Commit Handling** - MEDIUM priority
- **Repository Operations Enhancements** - MEDIUM priority
- **Security and Safety Enhancements** - HIGH priority

## Phase 1: Foundation & Core Enhancements (Months 0-6)

### 1.1 RunIt Observer Adapter Integration (HIGH) ✅ COMPLETED
**Milestone M1.1.1: Adapter Architecture Design** ✅ COMPLETED
- [x] Confirm RunIt adapter as standalone `commit-it-runit` package implementing CommitIt's `RepositoryObserver`
- [x] Design mapping from VCS operations to RunIt's Activity/Measurement/Observation/Occurrence/Snapshot triad
- [x] Establish adapter lifecycle and error handling patterns (Closure-based emitter injection)
- [x] Create adapter contract documentation (phpdoc and @api annotations)

**Milestone M1.1.2: Core Adapter Implementation** ✅ COMPLETED
- [x] Implement `CommitItRunitAdapter` class in `src/CommitItRunit/src/CommitItRunitAdapter.php`
- [x] Map all VCS operations to standardized occurrence names
- [x] Implement observer failure isolation and non-recursive behavior
- [x] Add integration tests with mock RunIt engine

**Milestone M1.1.3: RunIt Integration Testing** ✅ COMPLETED
- [x] Create unit test suite covering all observer lifecycle events
- [x] Test observer lifecycle events across all 25+ CommitIt operations
- [x] Validate error propagation and observer failure isolation
- [x] Verify all RepositoryOperation enum cases are mapped correctly
- [x] PHPStan (level 8) passes with zero errors
- [x] PHP-CS-Fixer passes with zero violations

**Deliverables**: `src/CommitItRunit/` package with `src/CommitItRunitAdapter.php`, `src/Model/Activity.php`, `src/Model/Snapshot.php`, `src/Model/Occurrence.php`, and unit tests in `tests/Unit/`

**Status Update**: The RunIt observer adapter is fully implemented as a standalone `it-pack/commit-it-runit` package:
- `CommitItRunitAdapter` implements CommitIt's `RepositoryObserver` and maps operations to RunIt's Activity/Snapshot/Occurrence triad
- Uses dependency injection via `Closure` emitters, keeping it decoupled from any concrete RunIt engine
- Preserves observer-failure isolation: emitter exceptions are caught and discarded on a non-recursive path
- Three model value objects (`Activity`, `Snapshot`, `Occurrence`) provide typed representations of RunIt concepts
- Operation name mapping covers all 25 `RepositoryOperation` enum values plus a default fallback
- Unit tests (33 test cases) cover: operation name normalization across all operations, Activity/Snapshot/Occurrence emission with correct context enrichment, activity ID correlation, observer failure swallowing on each lifecycle event, context enrichment precedence, and exhaustive RepositoryOperation coverage
- Integration tests will be added when the concrete RunIt execution engine becomes available

### 1.2 Advanced Working Tree Operations (HIGH)
**Milestone M1.2.1: Sparse Working Tree Support** ✅ COMPLETED
- [x] Implement `setSparsePatterns()`, `getSparsePatterns()`, `disableSparseCheckout()` on `WorkingTree` contract
- [x] Design sparse pattern value object (`SparsePattern`) with syntactic validation (non-empty, no control characters, length-bounded)
- [x] Add `SparsePattern` exception marker (`InvalidSparsePatternException`) with domain implementation
- [x] Add 3 `RepositoryOperation` enum cases (`SetSparsePatterns`, `GetSparsePatterns`, `DisableSparseCheckout`)
- [x] Implement git adapter via `git sparse-checkout init/set/list/disable` passthrough
- [x] Update `ObservedWorkingTree` decorator to observe all 3 new operations
- [x] Add observer lifecycle context (pattern list in `setSparsePatterns` context)
- [x] Create 8 unit tests (command formulation, failure mapping, pattern parsing, edge cases)
- [x] Create functional test (`testSparseCheckoutLifecycle`) exercising full lifecycle with real git
- [x] Add `SparsePattern` validation tests to `ValueObjectTest`
- [x] PHPStan (level 8) passes with zero errors
- [x] PHP-CS-Fixer passes with zero violations
- [x] All 246 tests pass (was 197 before Phase 1.1, added 49 new tests)

**Deliverables**: 
- `src/Domain/Value/SparsePattern.php` — Validated sparse-checkout pattern value object
- `src/Contract/Exception/InvalidSparsePatternException.php` — Exception marker interface
- `src/Domain/Exception/InvalidSparsePatternException.php` — Domain exception
- `src/Contract/WorkingTree.php` — Extended with 3 sparse-checkout methods
- `src/Contract/RepositoryOperation.php` — Extended with 3 new enum cases
- `src/Adapter/Git/GitPhpWorkingTree.php` — Sparse-checkout implementation
- `src/Application/ObservedWorkingTree.php` — Observation dispatch for new operations

**Implementation Notes**:
- The adapter delegates to `git sparse-checkout init/set/list/disable` — composition over subclassing, matching the existing worktree adapter pattern
- `getSparsePatterns()` returns an empty list when sparse-checkout is not configured (git error is caught internally), preserving CommitIt's "no silent failures" invariant for actual errors
- Sparse pattern validation is syntactic (non-empty, no control chars, length < 1024); git's own pattern semantics (cone vs non-cone mode) are left to the caller
- The `run()` helper now accepts an optional `$context` parameter for more specific error messages

**Milestone M1.2.2: Working Tree Security**
- [ ] Implement attribute-based access control for worktrees
- [ ] Add permission validation middleware
- [ ] Create worktree isolation boundaries
- [ ] Develop access control policy enforcement

**Milestone M1.2.3: Worktree Lifecycle Management**
- [ ] Implement automatic orphaned worktree cleanup
- [ ] Add worktree lifecycle hooks and monitoring
- [ ] Design worktree inheritance and branching strategies
- [ ] Create synchronization conflict detection/resolution

**Deliverables**: Enhanced `WorkingTree` implementation with new capabilities

### 1.3 Branch and Tag Management Enhancements (HIGH)
**Milestone M1.3.1: Branch Protection Framework**
- [ ] Implement Git-style branch protection policies
- [ ] Add branch requirement validation (minimum approvals, status checks)
- [ ] Create branch policy configuration value objects
- [ ] Develop branch protection enforcement logic

**Milestone M1.3.2: Branch Search and Indexing**
- [ ] Implement efficient branch searching and filtering
- [ ] Add branch metadata indexing for performance
- [ ] Create branch search API with advanced queries
- [ ] Develop branch search result aggregation

**Milestone M1.3.3: Tag Lifecycle Management**
- [ ] Implement automated tag cleanup and garbage collection
- [ ] Add tag promotion and staging workflows
- [ ] Create tag lifecycle hooks and notifications
- [ ] Develop tag validation and enforcement rules

**Deliverables**: Enhanced `Branch` and `Tag` value objects with protection and search capabilities

### 1.4 Security and Safety Enhancements (HIGH)
**Milestone M1.4.1: Advanced Path Validation**
- [ ] Implement comprehensive path traversal detection
- [ ] Add secure path normalization and validation
- [ ] Create path scope enforcement mechanisms
- [ ] Develop input sanitization and validation pipeline

**Milestone M1.4.2: Content Security Scanning**
- [ ] Implement secret detection in commit messages and file contents
- [ ] Add credential scanning and pattern matching
- [ ] Create security policy enforcement
- [ ] Develop security violation reporting

**Milestone M1.4.3: Access Control and Auditing**
- [ ] Implement fine-grained repository access controls
- [ ] Add comprehensive audit logging system
- [ ] Create security event aggregation and alerting
- [ ] Develop access control policy management

**Deliverables**: Security layer components integrated with existing CommitIt architecture

## Phase 2: Workflow & Integration Features (Months 6-12)

### 2.1 Multi-VCS Adapter Framework (MEDIUM)
**Milestone M2.1.1: Adapter Architecture Design**
- [ ] Define common adapter interface pattern for VCS systems
- [ ] Design adapter factory and registry patterns
- [ ] Create adapter contract documentation
- [ ] Establish adapter conformance testing framework

**Milestone M2.1.2: SVN Adapter Implementation**
- [ ] Implement `CommitItSvnAdapter` using phpseclib
- [ ] Map SVN operations to CommitIt domain objects
- [ ] Create SVN adapter tests using memory runner
- [ ] Validate adapter conformance with adapter tests

**Milestone M2.1.3: Mercurial and Perforce Adapters**
- [ ] Implement `CommitItHgAdapter` for Mercurial operations
- [ ] Create `CommitItP4Adapter` for Perforce operations (if feasible)
- [ ] Develop adapter migration tools and utilities
- [ ] Create cross-adapter compatibility tests

**Deliverables**: Multi-VCS adapter framework supporting multiple version control systems

### 2.2 Advanced Commit Handling (MEDIUM)
**Milestone M2.2.1: Commit Relationship Management**
- [ ] Implement commit chaining and relationship tracking
- [ ] Design fix, addresses, and references mapping
- [ ] Add commit graph relationship validation
- [ ] Create commit relationship query APIs

**Milestone M2.2.2: Merge and Cherry-Pick Automation**
- [ ] Implement advanced merge strategies
- [ ] Add automated conflict resolution mechanisms
- [ ] Create cherry-pick with validation and tracking
- [ ] Develop merge operation rollback capabilities

**Milestone M2.2.3: Commit Analysis and Visualization**
- [ ] Implement commit graph analysis algorithms
- [ ] Add commit history visualization utilities
- [ ] Create commit dependency and impact analysis
- [ ] Develop commit metadata aggregation and reporting

**Deliverables**: Enhanced commit handling capabilities with relationship management and analysis tools

### 2.3 Repository Operations Enhancements (MEDIUM)
**Milestone M2.3.1: Repository Fingerprinting**
- [ ] Implement repository state fingerprinting
- [ ] Add fingerprint validation and comparison
- [ ] Create fingerprint-based change detection
- [ ] Develop fingerprint storage and retrieval

**Milestone M2.3.2: Repository Migration Tools**
- [ ] Implement VCS-to-VCS migration utilities
- [ ] Add migration planning and validation
- [ ] Create migration rollback and recovery
- [ ] Develop migration monitoring and reporting

**Milestone M2.3.3: Repository Governance**
- [ ] Implement repository policy enforcement
- [ ] Add repository configuration management
- [ ] Create repository template system
- [ ] Develop repository analytics and metrics

**Deliverables**: Enhanced repository operations with migration, governance, and analytics capabilities

### 2.4 CI/CD Integration Patterns (MEDIUM)
**Milestone M2.4.1: CI Integration Package**
- [ ] Create `CommitItCi` package with CI-specific utilities
- [ ] Implement pre-commit hooks for commit message validation
- [ ] Add CI status reporters for GitHub Actions, GitLab CI
- [ ] Develop branch protection checkers

**Milestone M2.4.2: Integration Patterns Documentation**
- [ ] Document common CI/CD integration patterns
- [ ] Create GitHub Actions and GitLab CI examples
- [ ] Develop opinionated but configurable implementations
- [ ] Update documentation with integration examples

**Milestone M2.4.3: Integration Testing**
- [ ] Create integration test suites for CI/CD patterns
- [ ] Test CI/CD integration scenarios
- [ ] Validate integration configuration options
- [ ] Develop integration failure handling

**Deliverables**: CI/CD integration patterns and utilities package

## Phase 3: Scale & Advanced Features (Months 12+)

### 3.1 Performance Optimizations (LOW)
**Milestone M3.1.1: Connection Pooling**
- [ ] Implement git operation connection pooling
- [ ] Add connection lifecycle management
- [ ] Create connection reuse strategies
- [ ] Develop connection error handling

**Milestone M3.1.2: Lazy Loading and Caching**
- [ ] Implement lazy commit metadata loading
- [ ] Add repository path resolution caching
- [ ] Create batch operation optimizations
- [ ] Develop performance monitoring and metrics

**Milestone M3.1.3: Batch Operations**
- [ ] Implement multi-file operation batching
- [ ] Add batch operation validation and execution
- [ ] Create batch operation error handling
- [ ] Develop batch operation rollback

**Deliverables**: Performance improvements for high-volume scenarios

### 3.2 Advanced Branching Strategies (MEDIUM)
**Milestone M3.2.1: Advanced Branch Management**
- [ ] Implement advanced branching strategies
- [ ] Add branch configuration management
- [ ] Create branch inheritance and promotion
- [ ] Develop branch strategy validation

**Milestone M3.2.2: Virtual Branch Support**
- [ ] Implement virtual branching without full git operations
- [ ] Add GitHub Flow-like branching patterns
- [ ] Create virtual branch management utilities
- [ ] Develop virtual branch integration

**Deliverables**: Advanced branching strategies for professional workflows

## Common Dependencies and Infrastructure

### 3.3 Common Infrastructure Setup
**Milestone M3.3.1: Development Environment**
- [ ] Set up development baseline for new packages
- [ ] Configure Codeception, PHPStan, phparkitect, Infection
- [ ] Establish QA testing framework
- [ ] Create shared test fixtures

**Milestone M3.3.2: Testing Infrastructure**
- [ ] Implement test doubles and mocks for new features
- [ ] Create integration test fixtures
- [ ] Develop performance test scenarios
- [ ] Establish test coverage requirements

**Milestone M3.3.3: Documentation Infrastructure**
- [ ] Create feature specification documents
- [ ] Update architecture decision records
- [ ] Develop API documentation
- [ ] Create user guide examples

## Quality Assurance and Architecture

### 3.4 Architecture and Quality Gates
**Milestone M3.4.1: Architecture Validation**
- [ ] Update phparkitect architecture rules for new features
- [ ] Validate dependency boundaries
- [ ] Ensure no architectural violations
- [ ] Document architectural decisions

**Milestone M3.4.2: Quality Gates Implementation**
- [ ] Implement complete quality gate testing
- [ ] Create mutation testing strategies
- [ ] Establish performance benchmarks
- [ ] Develop security testing procedures

**Milestone M3.4.3: Continuous Integration**
- [ ] Integrate new features into CI/CD pipeline
- [ ] Set up automated testing and validation
- [ ] Establish deployment and monitoring
- [ ] Create quality metrics dashboard

## Success Metrics and Tracking

### 3.5 Success Metrics
- **Technical Metrics**:
  - 100% test coverage for all new features
  - Zero architecture violations
  - All quality gates passing
  - Backward compatibility maintained

- **Ecosystem Metrics**:
  - RunIt adapter completed and tested
  - Multi-VCS adapter framework established
  - CI/CD integration patterns documented
  - At least 3 horizontal and 3 vertical features implemented

- **Adoption Metrics**:
  - New features used in existing examples
  - Documentation examples updated
  - README updated with new capabilities
  - Community contributions tracked

## Risk Mitigation

### 3.6 Risk Management
- **Scope Creep**: Implement features in phases with clear interfaces
- **Architectural Drift**: Regular architecture reviews against deptrac rules
- **Quality Assurance**: Each feature must pass complete quality gates
- **Documentation**: Document all new public APIs and provide examples

## Next Steps

1. **Create detailed feature specifications** for each proposed feature
2. **Update `docs/IMPLEMENTATION_PLAN.md`** to reflect the new roadmap
3. **Add architecture decision records** for significant design decisions
4. **Create test fixtures** for new functionality
5. **Update `AGENTS.md`** with guidelines for implementing these features
6. **Establish monitoring** for feature implementation progress

This roadmap provides a comprehensive implementation plan for CommitIt's evolution while maintaining its core architectural principles and vendor-neutrality. The phased approach ensures focused development with clear milestones and deliverables.

## Implementation Timeline Summary

| Phase | Duration | Key Features | Status |
|-------|----------|--------------|--------|
| Phase 1 | 0-6 months | RunIt Adapter, Working Tree, Branch/Tag, Security | M1.1 Complete |
| Phase 2 | 6-12 months | Multi-VCS, Advanced Commits, Repository Ops, CI/CD | Planning |
| Phase 3 | 12+ months | Performance, Advanced Branching, Infrastructure | Planning |

Each milestone will be tracked with specific implementation tasks, testing requirements, and quality assurance checks to ensure successful delivery of CommitIt's enhanced feature set.