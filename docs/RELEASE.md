# Release Candidate Runbook

## Scope and verified workflows

The release provides tenant-safe lifecycle management for branches, plans, coaches and classes; members, memberships, partial payments and voiding; check-in/check-out; secure image uploads; printable local QR; member CSV import/export; practical filtered reports; operational settings; search, audit activity and dashboard. Demo data is created only when `APP_ENV=local`.

The white-label release also provides per-gym brand profiles, wallet ledgers, manual wallet charging, cafe menu/inventory and atomic wallet checkout. Online charging remains disabled until a PSP-specific adapter, signed callback and reconciliation runbook are approved.

## Local verification

```bat
scripts\artisan.bat optimize:clear
scripts\artisan.bat migrate:fresh --seed
scripts\artisan.bat test
scripts\artisan.bat route:list
npm run build
git diff --check
```

Local demo credentials: `admin@gym.test` and `demo@gym.test`, password `password`. Never enable local demo seeding in production.

## MySQL 8 test

Verified on MySQL 8.0.46: migration, seed and the full suite (91 tests, 237 assertions) are green. Create an isolated database whose name ends in `_test` (e.g. `gym_web_test`) with a restricted user, put the credentials in `.env.testing` (gitignored), then run:

```bash
# Linux/macOS
bash scripts/test-mysql.sh
# Windows
scripts\test-mysql.bat
```

Both scripts refuse any database that does not end in `_test`, run `migrate:fresh --seed --env=testing` and execute the suite through `phpunit.mysql.xml` (which uses the MySQL connection from `.env.testing`; the default `phpunit.xml.dist` pins in-memory SQLite). `migrate:fresh` is destructive and must never target a production database. Full details in `docs/MYSQL-VERIFICATION.md`.

## cPanel deployment

1. Select PHP 8.4 and enable PDO MySQL, mbstring, OpenSSL, tokenizer, XML, ctype, JSON, fileinfo, BCMath and ZIP when upload archives are required.
2. Create a MySQL 8 database/user with only privileges for this application.
3. Upload the release outside `public_html`; include `vendor` and `public/build` when Composer/Node are unavailable on the host.
4. Point the domain document root to the release `public` directory. If cPanel cannot do this, keep the application outside `public_html` and expose only the contents/front controller of `public`; never expose the project root or `.env`.
5. Create `.env`: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, MySQL credentials, `LOG_CHANNEL=daily`, `QUEUE_CONNECTION=database`, `CACHE_STORE=file`, `SESSION_DRIVER=file`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, and `SESSION_SAME_SITE=lax`.
6. Preserve one stable `APP_KEY` across releases. Do not print or commit it.
7. Make `storage` and `bootstrap/cache` writable by the account user; other source files should be read-only where practical.
8. Run `php artisan storage:link` for uploaded public images when that feature is enabled.
9. Run `php artisan migrate --force`; never run `migrate:fresh` or demo seeders.
10. Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`.
11. Enable AutoSSL and force HTTPS at the hosting/domain layer.
12. Smoke-test `/up`, login, gym selection, dashboard, check-in and a manager-only action.
13. Smoke-test wallet credit, insufficient-balance rejection, cafe checkout and tenant-specific appearance. Never test these against real member balances during deployment.

For an automated version of steps 5-10 plus backup, `current` symlink switch and rollback, use `scripts/deploy.sh` (see `docs/SERVER-TRANSFER.md` section 8).

## Cron

Daily session generation:

```cron
15 1 * * * cd /home/ACCOUNT/apps/gym && /usr/local/bin/php artisan classes:generate-sessions --days=30 >/dev/null 2>&1
```

Nightly backup with seven retained generations:

```cron
45 1 * * * cd /home/ACCOUNT/apps/gym && /usr/local/bin/php artisan app:backup --retention=7 >> storage/logs/backup.log 2>&1
```

Set `BACKUP_PATH` to a private directory outside the document root and `MYSQLDUMP_PATH` to the host binary. Copy at least one generation to another account/provider.

## Backup and restore

`app:backup` creates one checksummed archive containing the database plus public and private uploads. Every entry is AES-256 encrypted in production with `BACKUP_ENCRYPTION_PASSWORD`; the command uses a lock, honors retention and prints no credential. Run `app:backup-verify` against each new generation before treating it as recoverable.

Restore is intentionally manual: enable maintenance mode, verify backup checksum/date, create a fresh empty database, import the SQL using cPanel/phpMyAdmin or the MySQL CLI, restore uploads to `storage/app/public`, verify ownership, run `storage:link`, clear/rebuild caches, smoke-test, then disable maintenance mode. Always retain the pre-restore database and files until validation completes.

## Updates and rollback

Back up first, upload to a new release directory, reuse `.env` and persistent storage, migrate, cache and smoke-test before switching the document root/symlink. Roll back application code by switching to the previous release. Do not reverse destructive schema changes automatically; migrations must remain expand/contract compatible.

## Security and storage notes

Tenant records are fail-closed through Gym Context and global scopes. Platform admins do not receive tenant-data bypass. Manager writes, imports, settings, voids and token rotation require owner/manager authorization. Upload filenames are random, real MIME and extension are validated, SVG is rejected, and files live below `gyms/{gym_id}`. CSV exports are UTF-8 BOM and neutralize formula prefixes. Public member tokens are random but stored in plaintext so reception can match them; treat backups as sensitive.

## PWA behavior

The service worker caches only versioned build assets and public icon/offline files. It does not cache authenticated HTML, POST requests, CSRF responses, or tenant data. Offline mode is informational only.

## Known limitations

- Camera-based QR scanning is not included; manual member-code/token check-in remains available.
- Notifications are query-derived and read-only rather than persistent read/unread records.
- Offline mode is an informational shell and does not synchronize private data.
- Reports are operational, not tax/profit accounting reports.
- Full WCAG certification and a complete browser/device laboratory remain post-release work.
- Real MySQL execution must be completed with the guarded package in `docs/MYSQL-VERIFICATION.md`.

## CSV import

Download the template from the member import page, keep the exact eight headers, save as CSV UTF-8, preview, correct row-level errors, then confirm. The default limit is 500 rows and can be changed with `MEMBER_IMPORT_MAX_ROWS`. Duplicate membership code or non-empty email is skipped deterministically; valid rows are not rolled back because another row is invalid.

## QR and uploads

Printable cards render the random check-in token as a QR matrix locally using the MIT-licensed `qrcode` package 1.5.4; no external API receives member data. Rotation invalidates the old token. Member/coach avatars and gym logos accept JPEG, PNG or WebP up to 2 MB. The application serves them through tenant-scoped routes, so it remains usable when a public storage symlink is temporarily absent.
