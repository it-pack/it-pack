# M12.8 Supply-Chain Security

Supply-chain controls for the RunIt package. These are process and release controls; the matching
code-level controls are covered by the threat model (`docs/security/threat-model.md`).

## Dependency audit

- `composer audit` runs as part of `@security:audit` and the `@quality` gate.
- The CI pipeline re-runs the audit on every pull request and on a daily schedule.

## SBOM

- A Software Bill of Materials is generated for every release artifact (for example with
  `composer licenses` and a CycloneDX/SPDX export) and attached to the release.
- The SBOM covers direct and transitive dependencies, including the optional adapter
  dependencies (OpenTelemetry, CloudEvents, Swoole/OpenSwoole).

## Dependency review

- Pull requests require a dependency-review step that diffs `composer.json` /
  `composer.lock` and flags newly introduced or changed packages and versions.
- Optional adapter dependencies remain in `require-dev` / suggested `ext-*` only, so the
  production artifact never pulls in a telemetry or runtime extension unless a consumer opts in.

## CI action pinning

- All GitHub Actions are pinned to an immutable reference (`@<commit-sha>`) according to project
  policy; floating tags are not permitted in CI workflows.

## Secret scanning

- Repository secret scanning is enabled. The ItPack security contact (see `docs/SECURITY.md`)
  receives alerts.
- No secret is ever committed; release signing keys live in a protected secret store, never in the
  repository.

## Vulnerability reporting

- Suspected vulnerabilities are reported privately through the ItPack security contact, never as
  public issues (see `docs/SECURITY.md`).
- Security fixes take priority over feature work.

## Provenance

- Releases are produced from a protected branch by a pinned CI workflow and attached with
  checksums and an SBOM. Provenance attestation is enabled where the release platform supports it.
