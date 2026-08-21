#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

for f in examples/*.php; do
    echo "==> php $f"
    php "$f"
done
