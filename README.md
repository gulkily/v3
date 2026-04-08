# PHP Forum Rewrite

Minimal local test slice for the rewrite spec.

## Local Run

Rebuild the SQLite read model from the fixture repository:

```bash
php scripts/rebuild_read_model.php
```

Start the local PHP server:

```bash
php -S 127.0.0.1:8000 -t public public/router.php
```

Open these routes:

- `http://127.0.0.1:8000/`
- `http://127.0.0.1:8000/threads/root-001`
- `http://127.0.0.1:8000/posts/root-001`
- `http://127.0.0.1:8000/activity/`
- `http://127.0.0.1:8000/activity/?view=all&format=rss`
- `http://127.0.0.1:8000/instance/`
- `http://127.0.0.1:8000/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954`
- `http://127.0.0.1:8000/user/guest`
- `http://127.0.0.1:8000/compose/thread`
- `http://127.0.0.1:8000/compose/reply?thread_id=root-001&parent_id=root-001`
- `http://127.0.0.1:8000/account/key/`
- `http://127.0.0.1:8000/api/`
- `http://127.0.0.1:8000/api/list_index`
- `http://127.0.0.1:8000/api/get_thread?thread_id=root-001`
- `http://127.0.0.1:8000/api/get_post?post_id=root-001`
- `http://127.0.0.1:8000/api/get_profile?profile_slug=openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954`
- `http://127.0.0.1:8000/llms.txt`

The default repository source is [tests/fixtures/parity_minimal_v1](/home/wsl/v3/tests/fixtures/parity_minimal_v1). Override it with `FORUM_REPOSITORY_ROOT=/path/to/repo` if needed.

Set the local identity-hint cookie:

```bash
curl -X POST "http://127.0.0.1:8000/api/set_identity_hint?identity_hint=openpgp-demo"
```

## Tests

Run the current parser, rebuild, and app smoke tests:

```bash
php tests/run.php
```
