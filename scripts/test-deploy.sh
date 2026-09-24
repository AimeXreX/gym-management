#!/usr/bin/env bash
# =============================================================================
#  test-deploy.sh — tests for scripts/deploy.sh (Linux/macOS).
#
#  Exercises the dry-run mode, help output and error paths in a throwaway
#  sandbox. Dry-run must NEVER modify the sandbox (no .env, no symlink).
#
#  Run: bash scripts/test-deploy.sh
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY="$SCRIPT_DIR/deploy.sh"
PASS=0
FAIL=0
OUT=""
CODE=0

p() { PASS=$((PASS + 1)); printf '  \033[1;32m✓\033[0m %s\n' "$1"; }
f() { FAIL=$((FAIL + 1)); printf '  \033[1;31m✗\033[0m %s\n' "$1"; }

# run: capture combined output into OUT and exit code into CODE
run() { set +e; OUT="$("$@" 2>&1)"; CODE=$?; set -e; }

# make_sandbox: a minimal release with an artisan marker + the deploy script
make_sandbox() {
  local base
  base="$(mktemp -d)"
  mkdir -p "$base/releases/v1/scripts"
  cp "$DEPLOY" "$base/releases/v1/scripts/deploy.sh"
  touch "$base/releases/v1/artisan"
  printf '%s' "$base"
}

echo "Testing scripts/deploy.sh"

# 1. --help exits 0 and prints usage
run bash "$DEPLOY" --help
if [[ $CODE -eq 0 ]] && [[ "$OUT" == *"Usage:"* ]]; then p "help exits 0 and prints usage"; else f "help"; fi

# 2. unknown option is rejected
run bash "$DEPLOY" --bogus
if [[ $CODE -ne 0 ]] && [[ "$OUT" == *"unknown option"* ]]; then p "unknown option rejected"; else f "unknown option"; fi

# 3. missing PHP binary is rejected
run bash "$DEPLOY" --php /nonexistent/php --base /tmp
if [[ $CODE -ne 0 ]] && [[ "$OUT" == *"PHP binary not found"* ]]; then p "missing PHP rejected"; else f "missing PHP"; fi

# 4. dry-run on a valid release reports the plan and makes no changes
B="$(make_sandbox)"
run bash "$B/releases/v1/scripts/deploy.sh" --base "$B" --dry-run
if [[ $CODE -eq 0 ]] \
   && [[ "$OUT" == *"DRY RUN"* ]] \
   && [[ "$OUT" == *"would switch"* ]] \
   && [[ ! -f "$B/releases/v1/.env" ]] \
   && [[ ! -L "$B/current" ]]; then
  p "dry-run reports plan and makes no changes"
else
  f "dry-run no changes"
fi

# 5. dry-run with an env file reports the copy
printf 'APP_ENV=production\n' > "$B/prod.env"
run bash "$B/releases/v1/scripts/deploy.sh" --base "$B" --env-file "$B/prod.env" --dry-run
if [[ $CODE -eq 0 ]] && [[ "$OUT" == *"would copy"* ]]; then p "dry-run reports env copy"; else f "dry-run env copy"; fi

# 6. dry-run with a missing env file is rejected
run bash "$B/releases/v1/scripts/deploy.sh" --base "$B" --env-file "$B/missing.env" --dry-run
if [[ $CODE -ne 0 ]] && [[ "$OUT" == *"env file not found"* ]]; then p "missing env file rejected"; else f "missing env file"; fi

# 7. dry-run rollback reports the target without switching
mkdir -p "$B/releases/v0/scripts"
cp "$DEPLOY" "$B/releases/v0/scripts/deploy.sh"
touch "$B/releases/v0/artisan"
ln -s "$B/releases/v1" "$B/current"
run bash "$B/releases/v0/scripts/deploy.sh" --base "$B" --rollback --dry-run
if [[ $CODE -eq 0 ]] && [[ "$OUT" == *"would roll back"* ]] && [[ "$(readlink "$B/current")" == "$B/releases/v1" ]]; then
  p "dry-run rollback reports target, symlink unchanged"
else
  f "dry-run rollback"
fi

# 8. rollback without a current symlink is rejected
rm -f "$B/current"
run bash "$B/releases/v0/scripts/deploy.sh" --base "$B" --rollback
if [[ $CODE -ne 0 ]] && [[ "$OUT" == *"no current symlink"* ]]; then p "rollback without current rejected"; else f "rollback without current"; fi

# 9. invalid release path is rejected
run bash "$DEPLOY" /nonexistent/release --base "$B" --dry-run
if [[ $CODE -ne 0 ]] && [[ "$OUT" == *"release not found"* ]]; then p "invalid release rejected"; else f "invalid release"; fi

# 10. a release without artisan is rejected in dry-run
mkdir -p "$B/releases/v2/scripts"
cp "$DEPLOY" "$B/releases/v2/scripts/deploy.sh"
run bash "$B/releases/v2/scripts/deploy.sh" --base "$B" --dry-run
if [[ $CODE -ne 0 ]] && [[ "$OUT" == *"not a Laravel release"* ]]; then p "release without artisan rejected"; else f "release without artisan"; fi

rm -rf "$B"

echo
echo "deploy.sh tests: $PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
