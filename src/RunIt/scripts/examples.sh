#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

# The examples are the "Run this" targets referenced from docs/tutorial/* and
# docs/getting-started.md. This script is their smoke test: it keeps those links
# honest by actually executing every example.
#
# Optional-integration examples are skipped when their dependency is not
# installed, so `composer examples` stays green in minimal environments while
# still exercising every example wherever its dependencies are present.

class_exists() {
    local class="$1"
    php -r 'exit(class_exists($argv[1]) ? 0 : 1);' "$class" 2>/dev/null
}

for f in examples/*.php; do
    name="$(basename "$f")"

    # Syntax-check every example regardless of optional dependencies.
    if ! php -l "$f" >/dev/null 2>&1; then
        echo "==> SYNTAX ERROR in $f"
        php -l "$f"
        exit 1
    fi

    skip=0
    case "$name" in
        opentelemetry.php)
            if ! class_exists "OpenTelemetry\\SDK\\Trace\\TracerProvider"; then
                echo "==> SKIP $f (open-telemetry/sdk not installed)"
                skip=1
            fi
            ;;
    esac

    if [ "$skip" -eq 1 ]; then
        continue
    fi

    echo "==> php $f"
    php "$f"
done

echo "All runnable examples executed successfully."
