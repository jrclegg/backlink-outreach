# App Platform deployment preparation

This is preparation, not a deployed service. Use repository-root build context and Dockerfile `deploy/digitalocean/Dockerfile` for every component.

Create one web service on port 8080 with `/up` health check, and these independently managed workers (one instance each):

- `php artisan queue:work --queue=default --tries=3 --timeout=720 --max-time=3600`
- `php artisan queue:work --queue=outgoing --tries=1 --timeout=90 --sleep=1 --max-time=3600`
- `php artisan queue:work --queue=employer-quality --tries=1 --timeout=720 --max-time=3600`
- `php artisan queue:work --queue=employer-research,research --tries=1 --timeout=720 --max-time=3600`
- `php artisan schedule:work`

Use a managed PostgreSQL database. App Platform local files are ephemeral: do not use local SQLite for campaign data or a local scraper database. Configure a reachable `ACADEMIC_JOBS_FEED` instead. Research currently needs a feed/database when configured; migrate the source as well as the outreach app.

Set shared runtime variables: `APP_ENV=production`, `APP_DEBUG=false`, correct HTTPS `APP_URL`, `DB_CONNECTION=pgsql`, database credentials with TLS, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=database`, `LOG_CHANNEL=stderr`, `OUTREACH_AI_PROVIDER=anthropic`. Configure the organization's Anthropic key and supported model via AI settings after migration. Verify provider web search before enabling the campaign. Current personal Codex login is not bundled in this image.

Set `APP_KEY` as a shared secret to the EXISTING local application's key. Do not generate a different key: inbox passwords and other existing encrypted values need the original key. Never commit credentials. Validate trusted proxy handling and HTTPS URLs in the deployed dashboard.

Cutover:
1. Back up local SQLite and securely preserve APP_KEY. Initially keep server campaigns disabled and avoid running the scheduler until imported data is verified.
2. Create the PostgreSQL schema with a one-off `php artisan migrate --force`; export/import data preserving IDs, timestamps, JSON, encrypted values, contacts, suppression records, messages and campaign state. A cross-database import implementation is still required; migrations alone do NOT migrate existing records.
3. Verify counts, relationships, encrypted inbox access, sent history and duplicate protection. Do not import active queue reservations or replay completed outbound jobs.
4. Verify lead feed, AI research/drafting, rejection checks and a controlled send/reply test. Ensure no unrelated existing project campaigns become enabled.
5. Stop local automation before activating server automation, take a final consistent snapshot and import any final changes. Enable ONLY the intended employer campaign. Confirm daily cadence, 07:15 Europe/London and limit 50.
6. Check actual next-day batch execution, worker restarts, failed-job alerts, and managed database backup/restore.

The image has not yet been built or exercised on App Platform. Server migration, feed configuration, account connection and live validation remain required. File uploads need persistent object storage if used.
