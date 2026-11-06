# History purge

Accepting a retraction or an operator deletion removes the post from the current
tree, but its prose stays recoverable from Git history on the server. The purge
removes every post and signature path named by an accepted `delete` receipt from
all history, commits a [history rewrite record](../specs/history_rewrite_record_v1.md)
so old `/source/blob/<commit>/...` and `/source/commits/<commit>` links keep
resolving, and rebuilds the read model and static pages. Receipts, requests and
signatures keep their exact bytes. The purge never touches a path without a
receipt.

Run it deliberately and in batches, not after every acceptance. Every commit after
the first affected one gets a new identity.

## What the purge does not reach

Your own backups, other clones and mirrors, downloads already made, provider or
search-engine copies, and quotations in surviving posts. The command says so in
its output. A mirror that later pulls from this repository must re-clone: its old
history has diverged, and pushing or merging it back would reintroduce the purged
objects.

## Before you start

1. Confirm the deletions are accepted: the posts show the deletion notice and
   `records/post-removals/` or `records/post-retraction-acceptances/` holds their
   receipts. The purge targets receipts only.
2. Take a full backup of the repository **including `.git`**, and verify it
   restores. A completed purge cannot be undone any other way. If images are
   enabled, note that image backups bind to a repository revision; take the
   repository backup and the image backup together.
3. Expect writes to pause. `run` holds the same lock as every writer for the whole
   purge plus a full rebuild; the site keeps serving pages meanwhile. Synthetic
   measurements on a 100,000-post, 100,000-commit repository took about 35 s for
   the rewrite, prune and verification, before the rebuild; use `preview` for
   your own figure.
4. Git 2.34 or later on the host; no other dependency.

## Preview

```bash
./v3 purge-history preview --repository-root=/path/repository --database-path=/path/read-model.sqlite3
```

Preview changes nothing. It lists the receipts, the paths to purge, how many
commits exist and how many touch those paths, legacy posts that still lack a
timestamp record, anything that blocks a run, and the time a rewrite took on a
temporary clone (add `--skip-dry-run` to skip the clone). Exit code 0 means `run`
would proceed; 1 means it is blocked and the output says why:

- uncommitted changes in the working tree: commit or discard them;
- an interrupted instance import: finish it with `./v3 import-instance --resume`;
- legacy posts without `Created-At` and without a `records/post-timestamps/`
  record: bake their timestamps first with `./v3 bake-timestamps preview`, then
  `./v3 bake-timestamps run`, because the purge preserves author dates but a
  later history change might not. Run writes the dates rebuild already shows and
  commits only `records/post-timestamps/`; a post Git cannot date is listed and
  left unbaked, so date it by hand before purging;
- an earlier purge that did not finish (see Recovery);
- nothing to purge.

## Run

```bash
./v3 purge-history run --repository-root=/path/repository --database-path=/path/read-model.sqlite3 --static-html-root=/path/static_html
```

The command states the repository, the paths and the commit count and asks you
to type `yes`; anything else cancels with nothing changed. Pass `--yes` to skip
the question in a script; without a terminal and without `--yes` the run is
refused. The run snapshots every ref,
rewrites history, verifies that no ref still touches a purged path, and only then
discards the old objects: reflogs are expired, stale pseudo-refs removed and the
object database pruned. It then proves by object id that every old post blob and
every pre-purge commit is gone, commits the rewrite record with the subject
`Record history purge <time>`, rebuilds the read model, clears the activity commit
manifest cache, and regenerates static pages. While it works it reports each
phase on stderr with elapsed seconds, including a running commit count during
the rewrite and the line marking the point of no return, followed by the
rebuild's own progress. The result on stdout ends with `Verified` and the list
of copies the purge does not reach. Exit code 0 means verified.

## Verify

```bash
git -C /path/repository log --all -- records/posts/<path from the preview>   # prints nothing
git -C /path/repository log -1 --format=%s                                   # Record history purge <time>
```

Open a purged post's old permalink: the deletion notice, as before. Open an old
`/source/blob/<old commit>/<surviving path>` link from a bookmark or an old
activity page: it serves the file, with the response header
`X-Forum-Resolved-Commit` naming the new identity. `/source/commits/<old commit>`
shows the mapped commit with a `Requested-Commit` line.

## After the run

If images are enabled, take a fresh image backup. Earlier image backups verify
only against the pre-purge repository backup, because their manifest names the
pre-purge revision. Tell mirror operators to re-clone.

## Recovery

- **Interrupted before old history was discarded** (power loss, a killed
  process): the next `preview` reports the interrupted purge and its phase. The
  original refs are still intact; `run` restores them first and then purges.
- **Interrupted after pruning**, or **verification failed** because an object
  lingered (a `.keep` pack, an alternate object store, a second worktree): the
  rewritten history and its record are in place, but the purge is not proven
  complete. Restore the repository from the pre-purge backup, remove the extra
  reference, and purge again. `preview` keeps reporting the unfinished purge
  until then.
- **Record not committed** (the message says so and names the files): commit
  `records/history-rewrites/...` by hand, then run `./v3 rebuild`.
- **Site update incomplete** after the record commit: the read model is marked
  stale; run `./v3 rebuild` and, if static pages are served, `./v3 build-static`.
