.PHONY: quality test cs-check cs-fix analyse architecture mutation

# Monorepo root Makefile.
#
# Each src/<Pkg>/ has its own composer.json with per-package QA scripts.
# `make quality` runs `composer quality` in every package that defines it.
# Individual lanes are aliases that delegate to the same loop so `make <lane>`
# runs one lane across all packages that define it.

PACKAGES := $(shell for d in src/*/; do \
  pkg=$$(basename "$$d"); \
  if [ -f "$$d/composer.json" ] && grep -q '"quality"' "$$d/composer.json"; then \
    echo "$$pkg"; \
  fi; \
done)

define run_in_each
	@for pkg in $(PACKAGES); do \
		echo ""; echo "=== $$pkg ==="; \
		(cd "src/$$pkg" && $(1)) || exit 1; \
	done
endef

quality:
	$(call run_in_each,composer quality)

test:
	$(call run_in_each,composer test)

cs-check:
	$(call run_in_each,composer cs:check)

cs-fix:
	$(call run_in_each,composer cs:fix)

analyse:
	$(call run_in_each,composer analyse)

architecture:
	$(call run_in_each,composer architecture)

mutation:
	$(call run_in_each,composer mutation)
