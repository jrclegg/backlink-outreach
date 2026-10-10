# Backlink Outreach

A personal SEO outreach workspace for managing multiple websites and email accounts. Built with Laravel 12, Livewire, Tailwind and SQLite (other Laravel database drivers can also be configured).

## Daily workflow

1. Create a **Project** for each website. Open its workspace and add project context: audience, geography, useful resources and your preferred tone.
2. Open **Email Inboxes → Connect account**. Enter your email and click **Continue**. The app detects standard providers and supported custom-domain mail records, fills the connection settings, and suggests a matching project. Enter your mailbox/app password and click **Connect account**. Both sending and receiving must succeed before the account is saved, then the first sync starts automatically. If detection is inconclusive, choose your provider by name; server fields are only an advanced fallback.
3. Open **AI settings** to check your Codex connection (local mode), or configure an Anthropic API key if using that provider.
4. In a project, add a **target page** with a short brief. Ask AI to research opportunities for that particular page, with any preferences (for example, local resources or no paid placements).
5. Review relevance, attainability, uncertainty and source evidence. Contacts are AI suggestions that need checking. Open **Draft outreach**, edit the recipient and message, then **Save draft** or **Approve & send**.
6. Read and reply in **Project inbox**. AI drafts replies only when asked. New mail without a project match appears in **Unassigned mail** and can be assigned manually.
7. Enter the actual placement URL under an opportunity and check whether it links to the target page. Scheduled checks detect found and lost links.

## Scheduled employer sales outreach

Open a project and select **Employer outreach**. Set the employer audience, confirmed paid-listing offer, posting link, sending account, daily/weekly frequency, start date and contact cap. Preparation runs at the selected Europe/London time while the scheduler and research worker are running. Daily mode creates one batch per London date; weekly mode creates one per London week. Future batches can be scheduled in advance without researching or sending early. The Academic Ecology campaign aims for up to 50 new contacts each day at 09:00; a full 50 is not guaranteed if suitable new vacancies or contacts cannot be found.

`ACADEMIC_JOBS_FEED` can point to the public Academic Ecology Jobs feed for current hiring signals. Alternatively `ACADEMIC_JOBS_DATABASE` opens a local scraper SQLite database read-only. Feed import is bounded to its latest published records; database import is bounded to 200 records. Leads older than 60 days or with a known expired deadline are excluded. jobs.ac.uk vacancies receive priority with a link to its advertising rate page: this is evidence of use of a paid advertising channel, not proof of an individual's purchase or exact spend. Other channels remain unclassified until their pricing model is verified.

AI prioritises these hiring leads and retains only addresses found on a cited source page, with a separate specific job advert URL. Staff profiles and broad jobs lists do not count as adverts. Each research group returns at most eight contacts; larger batches use multiple groups. The batch cap is a maximum, not a guaranteed volume.

Enable **Automatically send emails that pass quality checks** to run without manual email review. Automation requires scheduled preparation to be enabled. Each draft is checked against freshly fetched contact and advert pages, the confirmed offer and the current date. The contact email must appear on the live page. A separate AI review must approve current recruitment, contact suitability, factual claims and personalisation, and quote supporting live evidence. Closed/unclear adverts, deadlines less than two days away, inaccessible pages, ungrounded claims, malformed subjects and drafts outside 70–220 words are skipped with a visible reason. These checks reduce risk but AI judgement is imperfect; the confirmed offer must remain accurate.

Approved automation uses the existing outgoing queue and hourly rate limits. The campaign cap counts approvals within the London day in daily mode, or calendar week in weekly mode. Daily mode also enforces the delivery-day cap if an older message is delayed. Only one introduction per vacancy is approved, including contacts with different email aliases. Vacancy URLs are normalised to ignore tracking parameters and jobs.ac.uk share-link variants while preserving job identifiers. Previously contacted email addresses are not reused in later batches. Previously contacted/replying/suppressed contacts remain excluded. Automatic sends stop if automation is disabled, the checked draft/evidence/offer changes, or the check is over 24 hours old. SMTP failures are never automatically resent. **Approve & send this email** and **Approve batch & send** remain available for manual mode. Automatic check failures stay unsent with their reason; they are not silently treated as passed.

Failed research and AI drafts have explicit retry controls. Ambiguous SMTP failures are never automatically resent. Replies stay in the project inbox. Keep the confirmed offer current; AI does not validate commercial claims independently. Email replies requesting no further contact should be added to the suppression list.

## Run locally

Requires PHP 8.2+, Composer, Node/npm, SQLite and PHP's cURL, OpenSSL, mbstring and DOM extensions. Webklex handles IMAP without the PHP IMAP extension.

For an existing installation:

```sh
composer install
npm install
php artisan migrate
npm run build
composer dev
```

For a new checkout, copy `.env.example` to `.env`, configure the database and run `php artisan key:generate` before migrating. Keep the application key stable: mailbox passwords and API keys are encrypted with it.

`composer automation` starts the mail, outgoing, quality, research and scheduler processes when the web server is already running. Keep it running on the local machine for unattended outreach; it does not survive shutdown.

`composer dev` starts the web server, Vite, the scheduler, an inbox sync worker (`default` queue), a sending worker (`outgoing` queue), and a separate research worker (`research` queue). Inbox synchronization and research do not block outgoing email. Do not use `QUEUE_CONNECTION=sync` for normal operation.

For deployment, supervise these workers and run the Laravel scheduler every minute:

```sh
php artisan queue:work --queue=default --tries=3 --timeout=720
php artisan queue:work --queue=outgoing --tries=1 --timeout=90 --sleep=1
php artisan queue:work --queue=employer-quality,employer-research,research --tries=1 --timeout=720
# Cron, once per minute:
php artisan schedule:run
```

Queue reservation timeouts must exceed the longest job timeout. Database/Redis/Beanstalk defaults have a minimum of 900 seconds. Configure SQS visibility timeout separately if using SQS. Restart workers after deployments. Serve over HTTPS when accessing beyond localhost.

## Use your Codex subscription locally

Install Codex CLI and run `codex login` with ChatGPT under the same computer account that runs PHP and the queue workers. Verify with `codex login status`. Set these values in `.env`:

```dotenv
OUTREACH_AI_PROVIDER=codex
CODEX_BINARY=codex
CODEX_MODEL=
```

Use an absolute `CODEX_BINARY` path if PHP cannot find it on PATH. Leave `CODEX_MODEL` empty to use the CLI default, or specify a model available to your Codex account. Run `php artisan config:clear` and restart existing workers after changing these settings. **AI settings → Check connection** checks the worker account's ChatGPT login; requests remain subject to your Codex allowance.

This mode is for your trusted personal local app. All app users share the machine's Codex account; do not expose it as a public service. It uses `codex exec` with an isolated temporary working directory, read-only sandbox, shell tools disabled, user config excluded, and structured output. Research enables live web search and requires a completed search event; drafting disables search. Temporary files are removed after each call. Inherited API keys are excluded and API-key-only logins are rejected. No credentials are copied into the app database.

Research has a 420-second Codex deadline and formatting/drafting a 180-second deadline, within the research worker timeout. A failure produces a visible error without saving unverified opportunities or sending mail. Set `OUTREACH_AI_PROVIDER=anthropic` to return to the original provider and configure its key in AI settings.

## Mail behavior

- Accounts use encrypted SMTP/IMAP credentials. Automatic detection maps known address domains and MX records to trusted provider presets; arbitrary MX hosts never become credential destinations. Unknown or mixed-provider records require a provider choice. Technical server settings stay hidden unless custom settings are explicitly enabled.
- Account labels and sender names are prefilled. A matching website project (or the only available project) is suggested automatically.
- Connecting and changing credentials tests both protocols before saving. Failed checks leave existing account details intact. Metadata-only edits preserve credentials without a network check.
- Gmail/Google Workspace and Yahoo use app passwords where the account permits them. Zoho requires IMAP access on the account/plan and an app password when two-factor authentication requires it. OAuth-only accounts are not supported in this version.
- Yahoo can reject an alias over SMTP with `550 No MIME From header matches auth mailboxes`, even after accepting the mailbox login. Verify the address in the same Yahoo account; webmail send-only setup does not establish SMTP permission. If rejection continues, use an outgoing mail provider authorized for the alias domain. Failed sends retain a redacted server reply and SMTP diagnostics in the conversation and logs.
- SMTP testing authenticates without sending an email. Connecting an account does not send any email, but queues its first incoming-mail sync after successful verification.
- The scheduler polls active inboxes every five minutes. **Sync now** queues an immediate poll. Initial sync reads the last 30 days from the configured incoming folder; subsequent polls overlap one day and deduplicate by account and Message-ID.
- Exact `In-Reply-To` / `References` headers match replies within the same account. Unmatched mail uses the account's default project, or stays unassigned. It is not matched to arbitrary projects by sender alone.
- Drafts are stored separately from approval. Only manually approved messages or employer messages approved by enabled automatic quality checks are sent. Duplicate send jobs are ignored. A network error after SMTP acceptance can be ambiguous, so failed sends are never automatically retried: inspect the provider's Sent folder before composing another attempt.
- The configured hourly limit applies to the new send queue. It also counts recent legacy campaign recipients. Legacy bulk campaigns remain separate and should not be run concurrently if you need a strict shared limit.
- Read/unread and archive state are local to this app; they do not modify remote mailbox flags or folders.
- This is a text-based outreach inbox: incoming HTML is reduced to text, remote images are not loaded, and outgoing messages use plain text. Attachments, CC/BCC, remote folder management, historical Sent-folder import and saving a copy to the provider's Sent folder are not implemented. Emails sent here remain visible in the app's conversation history.

## Research and backlink checks

Research fetches the target page through a public-address-only HTTP client and sends the page text and project brief to the configured AI provider. Codex or Claude uses live web search; a second request converts its research into opportunity cards. Cards must cite URLs in the research source set, and their placement URL must also appear in the source set. This grounds the suggestions but does not guarantee editorial acceptance or independently verify AI-inferred contact details.

Sources, research reports, instructions, errors and run status are retained. Failed requests are visible and can be retried by requesting research again. Anthropic research is limited to eight search uses per API turn and three continuation turns. Codex is instructed to use at most eight searches. Both save at most eight suggestions per run. Existing opportunities for the same target page and placement URL are retained without overwriting your outreach progress.

Backlink checks parse actual anchor elements and compare resolved URLs with the target page, ignoring fragments and trailing slashes. Checks do not currently evaluate `nofollow`, indexing, or domain authority. A found link is checked weekly; missing/lost/error results are checked daily. Fetch failures do not count as proof a link was removed.

## Validation

```sh
php artisan test
npm run build
php artisan view:cache
```

The application tests use an in-memory database, HTTP/process fakes and mocked mail transports. They cover approval, account selection, idempotent delivery/import, tenant boundaries, research source grounding, polling checkpoints, encrypted credential editing, provider presets and backlink detection. They do not send real email or spend API credits. Live account validation still requires your credentials and a deliberate send/reply test.

## Main code

- `app/Livewire/Workspace/ProjectWorkspace.php`: project research, conversations and reviewed drafting.
- `app/Services/Outreach/Assistant.php`: Provider routing and structured drafting.
- `app/Services/Outreach/CodexAssistant.php`: local Codex execution and evidence normalization.
- `app/Jobs/ResearchTargetPage.php`: background, source-backed research.
- `app/Services/Mail/ImapGateway.php` / `app/Services/Outreach/Mailbox.php`: incoming mail and threading.
- `app/Jobs/SendReviewedMessage.php`: approved outbound mail.
- `app/Jobs/CheckOpportunityLink.php`: page-specific backlink monitoring.
- `app/Livewire/Inboxes/InboxForm.php`: provider/account configuration.

Existing prospect imports, discovery tools and campaigns remain available. Individual prospect emails now enter the project conversation/send pipeline. The earlier campaign-specific AI and analytics components are separate from the new workspace.

## Provider references

- [Anthropic web search](https://platform.claude.com/docs/en/agents-and-tools/tool-use/web-search-tool)
- [Webklex IMAP queries](https://www.php-imap.com/api/query)
- [IONOS UK server settings](https://www.ionos.co.uk/help/index.php?id=2490)
- [Yahoo server settings](https://help.yahoo.com/kb/SLN4075.html)
- [Gmail app passwords](https://support.google.com/accounts/answer/185833)
- [Zoho IMAP](https://www.zoho.com/mail/help/imap-access.html) and [SMTP](https://www.zoho.com/mail/help/zoho-smtp.html)

- [Codex non-interactive mode](https://learn.chatgpt.com/docs/non-interactive-mode)
