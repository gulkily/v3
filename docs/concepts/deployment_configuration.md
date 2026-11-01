# Deployment configuration

Purpose: Give one deployment a single, persistent answer to "which site is this and where is its data" for the web app, CLI, cron and cache workers.
Scenario: QDB puts `FORUM_SITE_ID => 'qdb'` and its paths in `forum-private/qdb/secrets.php` beside its `qdb` checkout. Apache, `./v3` commands and the cron worker all serve QDB with no `SetEnv`, and a `git reset --hard` changes nothing. An environment variable still overrides the file; `./v3 status` shows each value's source and warns when tracked `public/.htaccess` carries local edits.
Sources: [lookup](../../src/ForumRewrite/Support/DeploymentSettings.php), [private file location](../../src/ForumRewrite/Support/PrivateConfig.php), [runbook](../runbooks/production_deploy.md#deployment-settings).
