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

Starting work without searching first is how the same fix gets made twice. If nothing matches, create a `#work` thread for the item, then claim it.

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

## Relationship to other lists

- `todo.txt` stays a scratchpad and is never edited by tooling.
- The [production feedback register](../plans/production_feedback_register.md) keeps owning migrated `PF-…` requests until they are reconciled into work items in a follow-up cycle.
- Railspike planning still happens in `docs/plans/`; a work item can link to its plan.
