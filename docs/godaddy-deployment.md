# GoDaddy deployment

Production URL: https://divertexcorp.com. The root redirects to `/careers`; employees sign in at `/login`.

## Layout

- Application release: `~/dvx-releases/20260928`.
- Active release symlink: `~/dvx-releases/current`.
- Public entry point: `~/public_html/index.php`, loading the active release's public entry point.
- Public assets: symlinks to the active release's `public` directory.
- Production configuration: `.env` inside the private release, mode `0600`. Never upload this file to `public_html` or commit it.
- Database: dedicated `dvxportal` database and user. Credentials are stored in the private production environment.
- Backups and deployment tooling: `~/dvx-deploy`, outside the document root.

Other domains' existing folders under `public_html` are preserved. The new rewrite rules are scoped to `divertexcorp.com` and `www.divertexcorp.com`. HTTPS and the canonical non-www domain are enforced.

## Runtime

Use `/opt/alt/php84/usr/bin/php` for Artisan and Composer. The web entry point uses the CloudLinux PHP 8.4 handler. Production dependencies are installed from lock files with development packages omitted.

Node is installed privately at `~/dvx-deploy/runtime/node/bin/node`. The deployment uses Node 22 and Linux dependencies for Sharp, TensorFlow WASM, and Human. Face verification runs on the server; do not replace it with client-only matching.

The cron configuration preserves previous jobs and adds the portal scheduler and a bounded queue worker every minute, protected against overlap with `flock`.

## Data and updates

The initial release copies local application tables and uploads. Sessions, caches, queued jobs, failed jobs, and password-reset tokens start empty. The encryption key is preserved so encrypted face enrollment data stays readable. Existing passwords remain unchanged. Passkeys created for `dvx.test` must be registered again on the production domain.

Before updating, back up the live database, `.env`, and `storage/app`. Build frontend assets, install production dependencies in a new private release, preserve live uploads/configuration, run non-destructive migrations, and verify before changing the active release symlink. Never overwrite live records with another local snapshot during routine updates.

Do not use automatic FTP upload of the whole repository: only public assets and the public entry point belong in `public_html`.

## Editor SFTP updates

The local `.vscode/sftp.json` configures the installed **Natizyskunk SFTP** extension for port 22 and `~/dvx-releases/current`. It contains local connection credentials and is excluded from Git. Do not share or commit it.

When this workspace is open in VS Code with the extension enabled, saving eligible files uploads them. A watcher also picks up changes made outside the editor in the application directories. Uploads use temporary files and OpenSSH atomic rename; remote deletion is disabled for both the watcher and sync.

The JSON excludes `.env` files, editor configuration, Git metadata, dependencies, all live storage, cached configuration, database dumps, local runtimes, test files, seeders, and generated development files. The dedicated `.vscode/sftp.ignore` intentionally does not inherit `.gitignore`, so `public/build` is included.

- For frontend changes, run `npm run build`, then wait until the SFTP Output panel finishes uploading before refreshing. Source TSX/CSS uploads alone do not update the compiled frontend.
- For cached configuration changes, use SSH to run `/opt/alt/php84/usr/bin/php artisan config:cache` in the active release. The local `.env` and cached config are never uploaded.
- New migrations and dependency manifests require an explicit server migration or dependency install; SFTP does not execute these operations.
- Changes made while VS Code is closed are not watched. Use **SFTP: Sync Local → Remote** after opening the workspace. Keep remote deletion disabled.
- Atomic uploads protect individual files, not an entire multi-file release. Use the staged-release procedure above for coordinated production changes, and keep older hashed build assets until those updates are verified.

## Email verification

During initial deployment, outbound connections to `smtp.gmail.com` on ports 465 and 587 timed out. With the owner's approval, production uses an authenticated `notifications@divertexcorp.com` mailbox on the hosting server's SMTP service, with STARTTLS and certificate validation. General replies go to `divertexcorp@gmail.com`; form notification messages also include the submitter's reply address. Recruitment notifications still go to the company Gmail inbox.

SMTP authentication passed and the server accepted one deployment verification email addressed to the company Gmail inbox. SMTP acceptance does not by itself verify final inbox placement.

Verified: HTTPS redirects, PHP 8.4 health endpoint, public pages and mobile UI, all imported application table counts, all seven upload checksums, readable encrypted face templates, and server-side facial inference rejecting a blank image. The owner confirmed a normal employee login opens the dashboard. Real-camera identity matching still requires an enrolled employee to test it on the live domain.
