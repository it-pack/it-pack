#!/usr/bin/env bash
#
# Strict quality gates for it-pack/hash-it.
#
# Enforces the stricter review bar (see docs/REVIEW.md) that PHP-CS-Fixer
# cannot auto-fix or detect on its own:
#   - F-3 : no `else` / `elseif` in src
#   - F-5 : no inline `\ItPack\HashIt\...` FQCN in code (must be `use`d)
#   - F-6 : no source/test line longer than 120 columns
#
# Run with: composer cs:strict
# Exits non-zero when any gate fails so CI turns red until the owning
# refactoring milestones (M3 / M4) clear the violations.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STATUS=0

echo "== Strict gate F-3: no else / elseif in src =="
# The rule is about *control flow*; docblock / line comments are excluded
# (mirrors the F-5 inline-FQCN grep below). The plain `grep -E` form is used
# so `set -e` keeps working when there are no matches.
if grep -rnE '\b(else|elseif)\b' "$ROOT/src" \
   | grep -vE '^[0-9]+:\s*\*' \
   | grep -vE '^[0-9]+://' \
   | grep -vE '^[0-9]+:#'; then
  echo "  -> F-3 violation(s) found (fix in M3)"; STATUS=1
else
  echo "  OK"
fi

echo "== Strict gate F-5: no inline FQCN in code =="
# Leading-backslash FQCN (the inline form), excluding docblock/comment lines
# and `use` / `namespace` declarations (those are legitimately fully qualified).
if grep -rn '\\ItPack\\HashIt\\' "$ROOT/src" \
   | grep -vE '^[0-9]+:\s*\*' \
   | grep -vE '^[0-9]+://' \
   | grep -vE '^[0-9]+:#' \
   | grep -vE '^[0-9]+:\s*(use|namespace) '; then
  echo "  -> F-5 violation(s) found (fix in M4)"; STATUS=1
else
  echo "  OK"
fi

echo "== Strict gate F-6: no line > 120 columns =="
# Generated Codeception support (_generated) and coverage output (_output) are
# excluded, mirroring the php-cs-fixer finder configuration.
if grep -rnP --binary-files=without-match '.{121}' "$ROOT/src" "$ROOT/tests" \
   | grep -vE -- '_generated/' \
   | grep -vE -- '_output/'; then
  echo "  -> F-6 violation(s) found (fix in M4)"; STATUS=1
else
  echo "  OK"
fi

if [ "$STATUS" -ne 0 ]; then
  echo "Strict gates FAILED"
  exit 1
fi

echo "Strict gates PASSED"
