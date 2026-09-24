#!/usr/bin/env bash
# =============================================================================
#  deploy.sh — production deployment for cPanel / shared hosting (no root).
#
#  Designed around the layout from docs/SERVER-TRANSFER.md:
#      $BASE/releases/<version>/   (one directory per release)
#      $BASE/current               (symlink -> releases/<version>)
#
#  Run it from inside a freshly extracted release (the script ships in the
#  release package via scripts/):
#      bash releases/20260817-115531/scripts/deploy.sh --env-file ~/apps/gym/.env.production
#
#  Or roll back to the previous release:
#      bash releases/20260817-115531/scripts/deploy.sh --rollback
#
#  Use --dry-run to validate and print the plan without changing anything
#  (handy before going live and for CI).
#
#  Safety: never runs migrate:fresh or demo seeders; takes a DB backup before
#  migrating; only switches the `current` symlink after checks pass; keeps the
#  previous release until you remove it manually.
# =============================================================================
set -euo pipefail

# ---- defaults ---------------------------------------------------------------
PHP_BIN="${GYM_PHP_PATH:-php}"
ENV_FILE=""
BASE=""
SKIP_BACKUP=0
NO_OPTIMIZE=0
ROLLBACK=0
DRY_RUN=0
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ---- helpers ----------------------------------------------------------------
log()  { printf '\033[1;34m[deploy]\033[0m %s\n' "$*"; }
ok()   { printf '\033[1;32m[ok]\033[0m    %s\n' "$*"; }
warn() { printf '\033[1;33m[warn]\033[0m  %s\n' "$*" >&2; }
fail() { printf '\033[1;31m[fail]\033[0m  %s\n' "$*" >&2; exit 1; }

usage() {
  cat <<'EOF'
Usage:
  bash scripts/deploy.sh [<release-archive.zip-or-dir>] [options]
  bash scripts/deploy.sh --rollback [options]

Options:
  --base DIR        Apps base directory (default: inferred from script location)
  --env-file FILE   Production .env copied into the release (reused across deploys)
  --php PATH        PHP binary (default: $GYM_PHP_PATH or php from PATH)
  --skip-backup     Do not take a DB backup before migrating (not recommended)
  --no-optimize     Skip config/route/view cache build
  --dry-run         Validate and print the plan without making any changes
  --rollback        Switch 'current' back to the previous release
  -h, --help        Show this help
EOF
  exit 0
}

# ---- argument parsing -------------------------------------------------------
ARGS=()
while [[ $# -gt 0 ]]; do
  case "$1" in
    --base)        BASE="$2"; shift 2 ;;
    --env-file)    ENV_FILE="$2"; shift 2 ;;
    --php)         PHP_BIN="$2"; shift 2 ;;
    --skip-backup) SKIP_BACKUP=1; shift ;;
    --no-optimize) NO_OPTIMIZE=1; shift ;;
    --dry-run)     DRY_RUN=1; shift ;;
    --rollback)    ROLLBACK=1; shift ;;
    -h|--help)     usage ;;
    -*)            fail "unknown option: $1" ;;
    *)             ARGS+=("$1"); shift ;;
  esac
done

command -v "$PHP_BIN" >/dev/null 2>&1 || fail "PHP binary not found: $PHP_BIN (set GYM_PHP_PATH or use --php)"

# ---- resolve layout ---------------------------------------------------------
# Script lives at $BASE/releases/<version>/scripts/deploy.sh
if [[ -z "$BASE" ]]; then
  BASE="$(dirname "$(dirname "$(dirname "$SCRIPT_DIR")")")"
fi
RELEASES_DIR="$BASE/releases"
CURRENT_LINK="$BASE/current"

# ---- rollback (with dry-run support) ----------------------------------------
if [[ "$ROLLBACK" == 1 ]]; then
  [[ -L "$CURRENT_LINK" ]] || fail "no current symlink to roll back from"
  current="$(readlink -f "$CURRENT_LINK")"
  target="$(find "$RELEASES_DIR" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' 2>/dev/null \
            | sort -rn | awk '{print $2}' \
            | while read -r d; do [[ "$(readlink -f "$d")" != "$current" ]] && { echo "$d"; break; }; done)"
  [[ -n "$target" ]] || fail "no previous release found to roll back to"
  if [[ "$DRY_RUN" == 1 ]]; then
    log "DRY RUN — no changes will be made"
    ok "would roll back: current -> $(basename "$target")"
    exit 0
  fi
  ln -sfn "$target" "$CURRENT_LINK"
  ok "rolled back: current -> $(basename "$target")"
  log "verify now, then remove the bad release manually if desired."
  exit 0
fi

# ---- resolve the release (read-only, reused by dry-run) ---------------------
resolve_release() {
  local arg="${1:-$(dirname "$SCRIPT_DIR")}"
  local dest sub
  if [[ -d "$arg" ]]; then
    echo "$(cd "$arg" && pwd)"; return
  fi
  if [[ -f "$arg" && "$arg" == *.zip ]]; then
    dest="$RELEASES_DIR/$(basename "$arg" .zip)"
    if [[ "$DRY_RUN" == 0 ]]; then
      mkdir -p "$dest"
      log "extracting $(basename "$arg") -> $RELEASES_DIR/$(basename "$arg" .zip)"
      unzip -q "$arg" -d "$dest"
      # The archive may contain a single top-level directory (e.g. gym-web-<ver>/).
      if [[ ! -f "$dest/artisan" ]]; then
        sub="$(find "$dest" -mindepth 1 -maxdepth 1 -type d | head -1)"
        [[ -n "$sub" && -f "$sub/artisan" ]] && dest="$sub"
      fi
    fi
    echo "$dest"; return
  fi
  fail "release not found: $arg (expected a directory or a .zip archive)"
}

# ---- dry run ----------------------------------------------------------------
if [[ "$DRY_RUN" == 1 ]]; then
  RELEASE="$(resolve_release "${ARGS[0]:-}")"
  [[ -f "$RELEASE/artisan" ]] || fail "not a Laravel release (no artisan in $RELEASE)"
  [[ -f "$RELEASE/public/build/manifest.json" ]] || warn "public/build/manifest.json missing — assets were not built"
  if [[ -n "$ENV_FILE" && ! -f "$ENV_FILE" ]]; then
    fail "env file not found: $ENV_FILE"
  fi

  log "DRY RUN — no changes will be made"
  log "release: $RELEASE"
  log "base:    $BASE"

  if [[ -f "$RELEASE/.env" ]]; then
    ok "would keep existing .env"
  elif [[ -n "$ENV_FILE" ]]; then
    ok "would copy $ENV_FILE -> .env"
  else
    warn "would create .env from .env.production.example (fill DB/MAIL/BACKUP credentials)"
  fi
  if grep -qE '^APP_KEY=.+$' "$RELEASE/.env" 2>/dev/null; then
    ok "APP_KEY would be preserved"
  else
    ok "would generate APP_KEY"
  fi
  ok "would set permissions (storage + bootstrap/cache writable, .env 0600)"
  if [[ "$SKIP_BACKUP" == 1 ]]; then
    warn "would skip backup (--skip-backup)"
  elif [[ -L "$CURRENT_LINK" ]]; then
    ok "would take pre-deploy DB backup (app:backup)"
  else
    warn "would skip backup (first deploy, no previous release)"
  fi
  log "would run: migrate --force, storage:link, optimize"
  log "would run: app:production-check"
  log "would switch: current -> $(basename "$RELEASE")"
  ok "dry run complete — nothing was modified"
  exit 0
fi

# ---- real deploy ------------------------------------------------------------
mkdir -p "$RELEASES_DIR"
RELEASE="$(resolve_release "${ARGS[0]:-}")"
[[ -f "$RELEASE/artisan" ]] || fail "not a Laravel release (no artisan in $RELEASE)"
[[ -f "$RELEASE/public/build/manifest.json" ]] || warn "public/build/manifest.json missing — assets were not built (run npm run build before packaging)"
[[ -f "$RELEASE/.env.production.example" ]] || warn "no .env.production.example in release"

log "deploying release: $RELEASE"
log "base: $BASE"

setup_env() {
  local env="$RELEASE/.env"
  if [[ -f "$env" ]]; then
    ok "existing .env found, keeping it"
    return
  fi
  if [[ -n "$ENV_FILE" ]]; then
    [[ -f "$ENV_FILE" ]] || fail "env file not found: $ENV_FILE"
    cp "$ENV_FILE" "$env"
    ok "copied $ENV_FILE -> .env"
  else
    cp "$RELEASE/.env.production.example" "$env"
    warn "created .env from .env.production.example — fill DB/MAIL/BACKUP credentials before serving"
  fi
  if ! grep -qE '^APP_KEY=.+$' "$env"; then
    ( cd "$RELEASE" && "$PHP_BIN" artisan key:generate --force --no-interaction )
    ok "generated APP_KEY"
  else
    ok "APP_KEY preserved"
  fi
}

setup_permissions() {
  chmod -R u+rwX,go+rX "$RELEASE" 2>/dev/null || true
  chmod -R u+rwX "$RELEASE/storage" "$RELEASE/bootstrap/cache"
  chmod 600 "$RELEASE/.env" 2>/dev/null || true
  ok "permissions set (storage + bootstrap/cache writable, .env 0600)"
}

maybe_backup() {
  [[ "$SKIP_BACKUP" == 1 ]] && { warn "skipping backup (--skip-backup)"; return; }
  if [[ -L "$CURRENT_LINK" ]]; then
    ( cd "$RELEASE" && "$PHP_BIN" artisan app:backup ) \
      || fail "pre-deploy backup failed — aborting (use --skip-backup to override)"
    ok "pre-deploy DB backup taken"
  else
    warn "first deploy — no previous release, skipping backup"
  fi
}

art() { ( cd "$RELEASE" && "$PHP_BIN" artisan "$@" ); }

setup_env
setup_permissions
maybe_backup

log "running migrations (--force)"
art migrate --force || fail "migrate failed"

art storage:link >/dev/null 2>&1 && ok "storage:link ready" || warn "storage:link skipped (may already exist)"

if [[ "$NO_OPTIMIZE" == 0 ]]; then
  log "building config/route/view cache"
  art optimize || fail "optimize failed"
  ok "caches built"
fi

log "running production preflight"
if art app:production-check; then
  ok "production-check passed"
else
  warn "production-check reported failures — review them before going live"
fi

ln -sfn "$RELEASE" "$CURRENT_LINK"
ok "current -> $(basename "$RELEASE")"

log "post-deploy: run"
log "  php artisan route:list"
log "  php artisan app:backup && php artisan app:backup-verify <latest.zip>"
log "then smoke-test /health, /login, gym selection, dashboard and check-in."
log "keep the previous release until smoke tests pass, then remove it manually."
