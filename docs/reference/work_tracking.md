# Work tracking

Work items are ZenMemes threads tagged `#work`. The site is where claims and status live; ideas can start anywhere (`todo.txt`, a thread, a chat) and are swept into items.

## Status

Each item has one current status: `open`, `active`, `done` or `dropped`. A post line such as

```
status: active - claude on feat/collapsible-nav
```

sets it. The latest valid line from a verified, approved member wins; an item with no line is `open`. Quoted lines (`> status: ...`) do not count. An `active` item with no thread activity for 7 days shows as `active (stale)` and may be reclaimed.

The thread page shows the status under the title. `/tags/work` lists all items.

## Before starting a fix

```sh
./v3 work search <words>          # or GET /work.txt?q=<words>&status=<status>
./v3 work claim <thread-id>       # refuses an item that is already active
```

Starting work without searching first is how the same fix gets made twice. If nothing matches, create a `#work` thread for the item (`python3 public/frm.py --site … thread --tags work …`, or jot it in `todo.txt` and sweep), then claim it.

When you finish, reference the item in the commit message with a trailer:

```
Work: <thread-id>
```

and set the status:

```sh
./v3 work status <thread-id> done --note "landed in <commit>"
./v3 work status <thread-id> dropped --note "duplicate of <thread-id>"
```

## When the site is unreachable

`claim` and `status` save the change in `state/work/pending.jsonl` and exit 3. Run `./v3 work flush` when the site is back; it posts queued changes in order and keeps anything it could not post. A write that reached the site but was not confirmed is not queued; follow frm.py's printed recovery steps instead.

## Identity

Writes go through `public/frm.py`, which creates or reuses a signing key. A new key's `status:` lines do not count until an operator approves the identity once; the CLI says so when this happens. Set `V3_WORK_FRM_STATE_DIR` to choose where frm.py keeps keys (GPG needs a fairly short path for its sockets), `V3_WORK_SITE` for another instance, and `V3_WORK_STATE_DIR` for the queue.

## Sweeping the inbox

```sh
./v3 work sweep            # list what would be created
./v3 work sweep --apply    # create the items
```

The sweep reads two inboxes:

- lines added to `todo.txt` since work tracking was introduced (`--since REV` to change), including uncommitted edits. A line starts an item; deeper-indented lines right after it become its details. Section headings with `---` underlines and `x`-marked done lines are skipped.
- threads tagged `#request` on the site (`/work.txt?kind=requests`).

Each created item is a `#work` thread whose root post starts with a `Source:` line (`todo.txt#<hash>` or `thread:<id>`). The sweep skips sources that already have an item, so rerunning it creates nothing new; editing a `todo.txt` line changes its hash and yields a new item. `todo.txt` itself is never edited. Lines that existed before work tracking are left for the reconciliation follow-up.

## Repository export

`/work.md` is a generated Markdown list: a header, then one line per item in thread-ID order, so a status change is a one-line diff. `./v3 work export` fetches it and commits it as `work.md` on the `work-export` branch (creating the branch if needed) and pushes; it makes no commit when nothing changed, and never touches the current checkout. `--no-push` commits locally only. Grep it with `git show origin/work-export:work.md`. The file can lag behind the site, so always check claims with `./v3 work search`.

## Relationship to other lists

- `todo.txt` stays a scratchpad and is never edited by tooling.
- The [production feedback register](../plans/production_feedback_register.md) keeps owning migrated `PF-…` requests until they are reconciled into work items in a follow-up cycle.
- Railspike planning still happens in `docs/plans/`; a work item can link to its plan.
