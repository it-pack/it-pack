# Magic-Value Inventory

`magic-values.php` inventories PHP string and numeric literals using
`it-pack/disenchant-it`. It is a **read-only, nonblocking review tool**, not an
automatic fixer: it does not modify or execute the PHP files being scanned.
The supplied Composer autoloader is executed, however, and must be trusted.

The raw inventory includes literals that may be legitimate, such as diagnostic
messages, definitions, and trivial values. A finding is not necessarily a defect.
Conversely, a clean report does not prove that no magic values exist: scanner
coverage has gaps, including interpolated strings. The tool does not currently
offer preview, apply, suppressions, or automatic constant creation.

## Requirements and setup

- PHP 8.5 or later and Composer.
- A trusted Composer autoloader providing DisenchantIt 1.1 and its dependencies.
- A trusted, stable source tree and a JSON policy.

The isolated tooling environment does not change a scanned package's runtime PHP
requirement. From the monorepo root, install the tool dependencies with:

```sh
composer install --working-dir=tools/repo/magic-values-composer
```

The repository ignores Composer lockfiles for this environment, so dependency
resolution is not pinned across installs. The RunIt package's separate PHP 8.4
development-dependency compatibility is not resolved by this setup.

## Run the inventory

From the monorepo root, run the RunIt Publication pilot:

```sh
php tools/repo/magic-values.php tools/repo/magic-values-composer/vendor/autoload.php tools/repo/magic-values-runit.json --root=.
```

Add `--json` for machine-readable output. The general invocation is:

```text
php magic-values.php AUTOLOAD POLICY [--json] [--root=DIR]
```

`AUTOLOAD` is the trusted Composer autoloader, and `POLICY` is the path to the
JSON policy. Without `--root`, policy paths are relative to the policy file's
directory. With `--root=DIR`, they are relative to that directory. Paths to the
autoloader, policy, and `--root` are resolved from the current working directory.
Each option can appear at most once. `--help` prints a concise usage summary.

| Exit code | Meaning |
| --- | --- |
| `0` | No findings meet the configured threshold. |
| `1` | One or more findings meet the threshold. |
| `2` | Configuration, dependency, parsing, scanning, or I/O error. |

Findings go to standard output; errors go to standard error. Treat exit code `1`
as an inventory result when running a nonblocking check, not as a tool error.

## Policy

Policies are JSON objects with version `1`. For example, when the repository
root is selected using `--root=.`:

```json
{
  "version": 1,
  "include": ["src/RunIt/src/Application/Publication"],
  "exclude": ["src/RunIt/src/Application/Publication/Generated"],
  "min_occurrences": 2,
  "mappings": []
}
```

| Field | Meaning |
| --- | --- |
| `version` | Required integer, currently `1`. |
| `include` | Required nonempty array of relative file or directory paths. |
| `exclude` | Optional array of relative paths to exclude, including descendants. |
| `min_occurrences` | Optional positive integer; defaults to `1` and filters groups by total occurrences. |
| `mappings` | Optional array of location-specific symbol suggestions, described below. |

Unknown fields and malformed values are errors. Paths are literal, normalized
relative paths, not globs. Each segment may contain ASCII letters, digits,
underscores, hyphens, and periods; absolute paths, `.` and `..` segments, and
repeated separators are rejected. Directory entries with other characters cause
an error during traversal. Overlapping includes are deduplicated, and at least
one PHP file must be selected.

Segments named `vendor`, `generated`, `_generated`, `fixture`, `fixtures`, or
`.git` are excluded case-insensitively. Policy exclusions match a path or its
descendants. Selected files must end in lowercase `.php`. Symlinks encountered
in selected paths are rejected; excluded paths are not traversed. Policy files
and selected sources have a 1 MiB runner read limit. DisenchantIt may impose a
smaller scanner limit, in which case the scan fails rather than reporting clean.

### Mapping suggestions

Mappings associate a detected occurrence with a suggested existing class
constant. They do **not** verify that the symbol exists or has the correct
visibility, type, value, or semantics, and they do not change the source.

```json
{
  "path": "src/Example.php",
  "line": 12,
  "kind": "string",
  "sha256": "<lowercase SHA-256 from the finding>",
  "symbol": "\\Example\\Names::VALUE"
}
```

Replace the placeholder with the finding's 64-character lowercase hash. The
`path` is relative to the selected root, `line` is a positive integer, and
`kind` is `string`, `int`, or `float`. The hash is of the scanner's **raw
literal token**, including lexical representation such as quotes or hexadecimal
notation, not a hash of the interpreted value. `symbol` must be a fully
qualified class constant name. Backslashes must be escaped in JSON.

Each mapping must match exactly one scanned occurrence by path, line, kind,
and hash; duplicate, stale, or ambiguous mappings produce exit code `2`.
Mappings are checked before `min_occurrences` filtering, even if their finding
would not appear in the final report. Review each suggestion in its semantic
context; equal literal values do not necessarily represent the same concept.

## Reports

The default text report has one line per finding, with its path, line, literal
kind, scanner role, raw-token hash, group occurrence count, and optional
suggested symbol. JSON output contains:

- `version`: report format version, currently `1`.
- `findings`: sorted occurrences with `path`, `line`, `kind`, `role`, `sha256`,
  nullable `hint_sha256`, and nullable `symbol`.
- `groups`: grouped `kind`, `role`, `sha256`, nullable `hint_sha256`,
  `occurrences`, and sorted `files`.

Groups combine occurrences with the same raw-token hash, kind, scanner role,
and hashed naming-context signature. They do not establish semantic equivalence.
The threshold applies to the group across all selected files. Literal contents
are not printed, but hashes of low-entropy values can be guessed. Paths and
suggested symbols are visible in reports. Handle reports accordingly.

## Safety and verification

Only scan trusted, stable trees. Symlink checks are not atomic with subsequent
filesystem reads, so concurrent filesystem replacement can race those checks.
Unparseable, unreadable, oversized, missing, or unsupported selected inputs
are errors rather than clean results. The tool intentionally does not use
DisenchantIt's rewriting APIs as an unattended fixer.

Run the tool's integration self-tests with:

```sh
composer test --working-dir=tools/repo/magic-values-composer
```
