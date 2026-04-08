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

The default server reads from [tests/fixtures/parity_minimal_v1](/home/wsl/v3/tests/fixtures/parity_minimal_v1). Override it with `FORUM_REPOSITORY_ROOT=/path/to/repo` if needed.

To enable local write testing without mutating the committed fixtures:

```bash
php scripts/init_local_repository.php
FORUM_REPOSITORY_ROOT=/home/wsl/v3/state/local_repository php scripts/rebuild_read_model.php
FORUM_REPOSITORY_ROOT=/home/wsl/v3/state/local_repository php -S 127.0.0.1:8000 -t public public/router.php
```

Set the local identity-hint cookie:

```bash
curl -X POST "http://127.0.0.1:8000/api/set_identity_hint?identity_hint=openpgp-demo"
```

Write API examples:

```bash
curl -X POST "http://127.0.0.1:8000/api/create_thread?board_tags=general&subject=Hello&body=Thread%20body"
curl -X POST "http://127.0.0.1:8000/api/create_reply?thread_id=root-001&parent_id=root-001&body=Reply%20body"
curl -X POST --data-urlencode "public_key@tests/fixtures/parity_minimal_v1/records/public-keys/openpgp-0168FF20EB09C3EA6193BD3C92A73AA7D20A0954.asc" "http://127.0.0.1:8000/api/link_identity?bootstrap_post_id=root-001"
```

## Tests

Run the current parser, rebuild, and app smoke tests:

```bash
php tests/run.php
```
