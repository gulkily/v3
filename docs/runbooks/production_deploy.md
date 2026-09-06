# Production Deploy Runbook

This runbook describes the intended production deployment model for the PHP forum rewrite.

## Deployment Model

The intended production shape is:

- Apache serves `public/` as the `DocumentRoot`
- PHP handles dynamic requests through `public/index.php`
- Apache serves eligible sibling `*.html` artifacts directly when they exist
- the canonical writable repository lives outside `public/`
- derived state under `state/` is writable by the web user

This application assumes a conservative shared-host style environment with:

- PHP 8.1+
- PDO SQLite
- standard filesystem functions
- shell access sufficient for non-interactive `git` commands
- Apache with `mod_rewrite`

## Required Directory Layout

One workable layout:

```text
/srv/forum-rewrite/
  app/                      application checkout
    public/
    src/
    templates/
    scripts/
  repository/               writable canonical content checkout
    records/
    .git/
  state/
    cache/
    private/
    forum-rewrite.lock
    read_model_stale.json
```

Recommended mapping:

- application root: `/srv/forum-rewrite/app`
- Apache `DocumentRoot`: `/srv/forum-rewrite/app/public`
- writable repository root: `/srv/forum-rewrite/repository`
- read-model database: `/srv/forum-rewrite/state/cache/post_index.sqlite3`

Optional runtime setting:

- `FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS`: seconds a request waits for the shared write/read-model lock before returning a busy error. The default is `5`.
- `FORUM_UNICODE_AUTHORED_TEXT`: when set to `true`, subject/body prose may contain visible UTF-8 text such as Cyrillic. The default is disabled.
- `FORUM_APP_VERSION_NOTIFICATION`: when set to `false`, disables browser-side app version polling and the reload banner. The default is enabled.
- `LLM_CONVERSATION_RECORDING_ENABLED`: controls private exact-prompt/response capture. The default is enabled.
- `LLM_CONVERSATION_UI_ENABLED`: controls approved-user/operator web visibility of captured exchanges. The default is enabled.
- `LLM_EXCHANGE_DATABASE_PATH`: optional private SQLite path; defaults to `<application-root>/state/private/llm_exchanges.sqlite3`.

## Writable Paths

The web user must be able to write:

- the canonical repository checkout at `FORUM_REPOSITORY_ROOT`
- the parent directory of `FORUM_DATABASE_PATH`
- the lock file directory next to `FORUM_DATABASE_PATH`
- `state/private/agent-reply/` under the application root if agent reply fulfillment is enabled
- the parent directory of `LLM_EXCHANGE_DATABASE_PATH` if LLM conversation recording is enabled
- sibling static artifacts in `public/` if production uses `public/*.html`

If sibling `public/*.html` artifacts are used and writes are enabled, the application must be able to invalidate affected artifacts after successful writes.

## Environment Variables

Use these in production:

- `FORUM_REPOSITORY_ROOT`
- `FORUM_DATABASE_PATH`
- `FORUM_PUBLIC_ARTIFACT_ROOT`

Suggested values:

```text
FORUM_REPOSITORY_ROOT=/srv/forum-rewrite/repository
FORUM_DATABASE_PATH=/srv/forum-rewrite/state/cache/post_index.sqlite3
FORUM_PUBLIC_ARTIFACT_ROOT=/srv/forum-rewrite/app/public
```

`FORUM_STATIC_HTML_ROOT` remains available for separate static roots, but the primary production model for this repo is sibling artifacts in `public/`.

LLM exchange records are private runtime data. Keep `LLM_EXCHANGE_DATABASE_PATH` outside `public/`, the canonical repository, and the published read-model database; restrict the file to the deployment/web users. The exchange UI is available only to approved viewers and can be disabled independently with `LLM_CONVERSATION_UI_ENABLED=false`.

## Site Profile

`FORUM_SITE_ID` selects which `SiteProfileRegistry` entry (site name, default theme, composer copy) this deployment renders. Values: `zenmemes`, `chouse`. Unset, empty, or unrecognized values resolve to `zenmemes`.

Set it alongside the three path variables above, per vhost:

```text
FORUM_SITE_ID=zenmemes
```

It only selects branding — it does not select content or database paths. Those remain on `FORUM_REPOSITORY_ROOT`, `FORUM_DATABASE_PATH`, and `FORUM_PUBLIC_ARTIFACT_ROOT`, set independently per vhost. A vhost with a mismatched `FORUM_SITE_ID` and content paths is a misconfiguration, not a supported mode; the two must be kept paired by whoever edits the vhost config.

Precedence and rollback match the other `FORUM_*` variables in this runbook: environment variable wins, code default (`zenmemes`) applies when absent, and rollback is deleting the `SetEnv` line.

For local/CLI runs, set the same variable before the command:

```bash
FORUM_SITE_ID=chouse ./v3 start
```

Omitting `FORUM_REPOSITORY_ROOT`, `FORUM_DATABASE_PATH`, and `FORUM_STATIC_HTML_ROOT` in that case auto-initializes a site-scoped local sandbox (`state/local_repository_chouse` and matching database/static paths) separate from the default `zenmemes` sandbox, so both profiles can be developed from the same checkout.

## LLM Provider Config

Post analysis and agent reply drafting use provider-neutral `LLM_*` private config. Create or inspect the private config with:

```bash
./v3 private-config --force
./v3 private-config view
./v3 private-config refresh-template
```

Use `refresh-template` after upgrades to rewrite the private config with current comments and provider examples while preserving existing effective values.

Supported providers:

- `dedalus`: OpenAI-compatible Dedalus endpoint, the default for existing installs
- `openai`: direct OpenAI API
- `openrouter`: OpenRouter API, with optional attribution headers
- `anthropic`: direct Anthropic Messages API
- `stub`: deterministic local analysis for smoke tests and offline operation
- custom OpenAI-compatible gateways such as LiteLLM, vLLM, llama.cpp, or internal routers

The common keys are:

```php
'LLM_PROVIDER' => 'dedalus',
'LLM_API_KEY' => 'replace-with-real-key',
'LLM_API_BASE_URL' => 'https://api.dedaluslabs.ai',
'LLM_MODEL' => 'openai/gpt-5-nano',
'LLM_TIMEOUT_SECONDS' => 60,
'LLM_EXTRA_HEADERS' => [],
'LLM_POST_ANALYSIS_PROMPT_PATH' => 'prompts/dedalus_post_analysis_system.txt',
```

Provider examples:

```php
// Direct OpenAI
'LLM_PROVIDER' => 'openai',
'LLM_API_BASE_URL' => 'https://api.openai.com',
'LLM_MODEL' => 'gpt-5-nano',

// Direct Anthropic
'LLM_PROVIDER' => 'anthropic',
'LLM_API_BASE_URL' => 'https://api.anthropic.com',
'LLM_MODEL' => 'claude-haiku-4-5-20251001',

// OpenRouter
'LLM_PROVIDER' => 'openrouter',
'LLM_API_BASE_URL' => 'https://openrouter.ai/api',
'LLM_MODEL' => 'openai/gpt-5-nano',
'LLM_EXTRA_HEADERS' => [
    'HTTP-Referer' => 'https://forum.example',
    'X-Title' => 'Forum',
],

// LiteLLM or another OpenAI-compatible gateway
'LLM_PROVIDER' => 'litellm',
'LLM_API_BASE_URL' => 'https://llm-gateway.example',
'LLM_MODEL' => 'openai/gpt-5-nano',
```

OpenAI-compatible providers are called at `LLM_API_BASE_URL + /v1/chat/completions`. Anthropic is called at `LLM_API_BASE_URL + /v1/messages`. Direct `DEDALUS_*` LLM settings remain supported as fallbacks for current deployments, but new config writes use `LLM_*`.

## Agent Reply Requests

Approved users can request a `reply-agent` response from eligible post cards. The button records a durable request in SQLite and returns quickly; it does not wait for provider analysis or canonical posting.

Fulfillment is handled by a periodic PHP command. A typical cron entry is:

```cron
* * * * * cd /srv/forum-rewrite/app && php scripts/run_agent_reply_requests.php --quiet --limit=10 >> /var/log/forum-agent-replies.log 2>&1
```

The deployed checkout can also print the current-path reference:

```bash
./v3 agent-reply cron
```

The command uses the same repository, database, private LLM config, `reply-agent` key directory, and artifact paths as the web app. It exits successfully if another fulfillment run is already active, and queued-row claims prevent duplicate `reply-agent` posts for the same requested post content.

Without `--quiet`, the command prints repository/database/artifact paths, queue counts before and after the run, claimed-row count, one processing/result line per request, reason totals, and elapsed time. `--quiet` suppresses successful STDOUT for cron while preserving STDERR errors.

Useful manual commands:

```bash
./v3 agent-reply test
./v3 agent-reply test-local
php scripts/run_agent_reply_requests.php --dry-run
php scripts/run_agent_reply_requests.php --limit=10
php scripts/run_agent_reply_requests.php --post-id=<post-id>
./v3 agent-reply status <post-id>
./v3 agent-reply status --limit=25
./v3 agent-response cron run --limit=10
./v3 agent-response cron run --dry-run
```

## Site Feature Flags

The site exposes registered public flags at `/tools/feature-flags/`.

Root-approved users can change mutable site flags from that page. Successful changes update:

```text
records/instance/feature-flags.txt
```

and commit the change to the content repository git log.

Runtime precedence is:

1. environment/private override
2. git-backed site value
3. code default

Use `FORUM_*` environment variables for emergency or deployment-level overrides. While an environment variable is present, the corresponding flag is effectively pinned by the process and the site UI reports the environment source.

Audit site-level changes with:

```bash
git -C /srv/forum-rewrite/repository log -- records/instance/feature-flags.txt
git -C /srv/forum-rewrite/repository show <commit>:records/instance/feature-flags.txt
```

Rollback options:

- set the previous value through `/tools/feature-flags/`
- or revert the relevant content-repository commit

If production serves prebuilt static HTML artifacts, rebuild artifacts after changing flags outside the web write path. Changes made through the web path invalidate common shell/tool artifacts automatically.

## App Version Notification

`FORUM_APP_VERSION_NOTIFICATION=false` disables the browser-side `/api/version` polling and the "A new version is available." reload banner.

The default is enabled. `/api/version` remains available when the notification is disabled.

If production serves prebuilt static HTML artifacts, rebuild those artifacts after changing this flag so rendered pages include or omit the notification markup consistently.

## HTTP, HTTPS, and Browser OpenPGP

Plain HTTP remains a supported production access path. The default Apache example serves the app on port 80 and must not be replaced with a forced HTTP-to-HTTPS redirect unless the local operator intentionally accepts the extra TLS failure modes.

Do not enable HSTS by default. `Strict-Transport-Security` can prevent users from reaching the site after certificate expiry, hostname changes, proxy mistakes, subdomain gaps, captive-portal interference, or other TLS failures.

Browser-held OpenPGP identity uses:

- `public/assets/openpgp_loader.js`
- `public/assets/openpgp.min.js` for OpenPGP.js v6 on HTTPS and secure loopback origins
- `public/assets/openpgp.v5.11.3.min.js` as the patched v5 fallback on public HTTP

OpenPGP.js v6 is preferred, but browsers do not expose `crypto.subtle` on public HTTP origins. The loader therefore uses the v5 fallback on public HTTP so authored browser-identity posting can still work where the legacy bundle works.

If browser OpenPGP is unavailable, compose forms expose an explicit anonymous submit button. That path submits with an empty `author_identity_id`; the server writes a normal canonical post without `Author-Identity-ID`. This is intentional and is separate from authored OpenPGP posting.

Server-side user identity generation is intentionally out of scope. Do not generate, store, or manage user private keys on the server for this fallback.

## Unicode Authored Text Rollout

`FORUM_UNICODE_AUTHORED_TEXT=true` enables visible UTF-8 prose in post subjects and bodies.

Scope:

- affected: human-authored post `subject` and `body`
- unchanged: post IDs, thread IDs, parent IDs, board tags, reaction tags, profile slugs, identity IDs, routes, and artifact paths

The server still rejects invalid UTF-8, control characters, format characters, bidirectional controls, private-use characters, noncharacters, unsupported spacing characters, and symbols such as emoji.

Operational notes:

- keep the flag disabled for first deploys unless Unicode prose has been explicitly tested on the target host
- verify `git diff`, rebuild scripts, static artifact generation, RSS, and terminal inspection all run under a UTF-8-capable locale
- PHP `intl` is optional in this implementation; when it is unavailable, combining-mark input is rejected instead of normalized, while precomposed readable Unicode such as normal Cyrillic remains supported
- rollback is `FORUM_UNICODE_AUTHORED_TEXT=false`; existing Unicode records remain valid UTF-8 canonical text and should continue to parse and render

Rollout smoke test:

1. enable `FORUM_UNICODE_AUTHORED_TEXT=true`
2. create a test thread with subject `Привет` and body `Привет мир`
3. verify the thread page, post page, activity page, RSS feed, read-model rebuild, and static artifact build
4. verify a post with a zero-width or bidirectional control character is rejected
5. disable the flag if submit-time Unicode acceptance must be rolled back

## Automatic Agent Replies

Automatic replies can be disabled with:

```text
DEDALUS_AGENT_REPLIES_ENABLED=false
```

Agent replies are drafted by the post-analysis prompt and posted from the stored `engagement.suggested_response` when respondability gates pass. Production uses the configured `LLM_*` provider for that analysis call.

On first successful agent reply, the app bootstraps a canonical `reply-agent` OpenPGP identity and stores the private key under:

```text
<application-root>/state/private/agent-reply/
```

This directory must not be under `public/` and should be readable only by the deployment user and web user. To rotate the key, disable automatic replies, archive the old private key, remove or supersede the canonical `reply-agent` identity through an operator-reviewed migration, rebuild the read model, then re-enable replies so a new key can be bootstrapped.

## First-Time Setup

1. Check out the application code.
2. Create the writable canonical repository checkout.
3. Ensure the repository is a real git checkout with `records/` and `.git/`.
4. Create the writable state directory.
5. Configure Apache to serve `public/`.
6. Set the production environment variables.
7. Run the initial read-model rebuild.
8. Optionally build sibling static HTML artifacts.

Example commands:

```bash
php scripts/rebuild_read_model.php /srv/forum-rewrite/repository /srv/forum-rewrite/state/cache/post_index.sqlite3
php scripts/build_static_artifacts.php /srv/forum-rewrite/repository /srv/forum-rewrite/state/cache/post_index.sqlite3 /srv/forum-rewrite/app/public
```

## Pre-Launch Checklist

- Apache `DocumentRoot` points to `public/`
- `AllowOverride` permits `.htaccess` if using the checked-in rewrite file
- `mod_rewrite` is enabled
- PHP can open PDO SQLite
- the writable repository is a git checkout
- the web user can create commits in the writable repository
- the web user can write the read-model database and lock files
- the web user can invalidate `public/*.html` artifacts if sibling artifacts are enabled
- `/api/read_model_status` returns `status=ready`
- HTTP requests to `/account/key/`, `/compose/thread`, and `/assets/openpgp_loader.js` return app/asset responses, not forced HTTPS redirects
- default responses do not emit `Strict-Transport-Security`

## Manual Verification

Before launch, verify:

- board route loads
- thread route loads
- profile route loads
- account route loads
- compose thread/reply routes load
- anonymous queryless board/thread/profile requests can be served from sibling `*.html` artifacts
- cookie-bearing requests bypass static artifacts and fall back to PHP
- thread creation works
- reply creation works
- authored browser identity bootstrap works on HTTPS or secure loopback with the v6 OpenPGP path
- authored browser identity bootstrap works on public HTTP with the v5 OpenPGP fallback where supported by the browser
- explicit anonymous compose works when browser OpenPGP is unavailable

## Recommended Launch Sequence

1. deploy application code
2. verify Apache config
3. verify writable repository/config paths
4. rebuild read model
5. build static artifacts
6. open `/api/read_model_status`
7. smoke-test core read routes
8. smoke-test one write flow

## Related Docs

- [operator_recovery.md](/home/wsl/v3/docs/runbooks/operator_recovery.md)
- [apache_vhost.conf](/home/wsl/v3/docs/examples/apache_vhost.conf)
- [env.production.example](/home/wsl/v3/docs/examples/env.production.example)
