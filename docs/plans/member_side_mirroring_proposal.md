# Member-side mirroring: what it means and why it matters

Written 2026-10-30. This is a proposal that explains an idea raised as gap 1 in
[the Nostr relay debate and how v3 answers it](../product/nostr_relay_debate_and_v3.md).
It is not an approved plan, does not start a Railspike cycle and does not
authorize implementation. A Step 1 would compare the options at the end.

## The claim v3 makes, and what backs it today

v3 says a community can take its history and move. The architecture backs this:
every canonical fact is a plain-text file in Git, and the database and pages are
rebuilt from it. The claim holds if someone other than the operator holds a
current copy. Today that is true only in a weak sense:

- **Backup page downloads** give a TAR or ZIP of the committed tree and the
  read-model SQLite file. Since the 2026-10-24 one-shot, these archives omit
  `.git`: no history, no commit evidence, only the current record plus a small
  snapshot manifest ([history-free exports](repository_content_export_one_shot_summary.md)).
- **`./v3 import-instance`** lets another instance pull that archive and merge
  the public records into its own repository, verifying signatures and keeping
  its own approval authority. It is one-way, additive and manual; scheduling was
  deferred ([instance content sync](instance_content_sync/instance_content_sync_step1_solution_assessment.md)).
- **Full Git history** is backed up only by the operator, by whatever means the
  operator chooses. The runbooks describe how to back up the private SQLite
  stores but do not prescribe an off-host copy of the repository.

So the exit promise currently depends on the operator continuing to offer
downloads, on someone remembering to click, and on the operator alone holding
the history. A hostile or absent operator defeats all three. This is the same
weakness the Hacker News thread found in Nostr: relays "heal" only because
clients hold copies and know where else to look.

## What "member-side mirroring" means

A member, or a second steward, keeps a current, verifiable copy of the
community's canonical record without needing the operator's ongoing cooperation
beyond ordinary read access, and can stand up a working instance from it.

Three distinct things are often lumped together. They should stay separate:

| Term | What it holds | Who runs it | Writable? |
| --- | --- | --- | --- |
| **Mirror archive** | Successive downloaded snapshots, verified and diffed | Any member with a disk | No |
| **Mirror instance** | A running read-only v3 fed by scheduled import | A second steward | No |
| **Fork** | A mirror that becomes the community's new home | A new steward | Yes, with its own trust anchors |

A mirror is a copy, not authority. A fork re-seeds its approval roots; the
original operator's acceptances do not transfer as authority, only as records.
Import already works this way ("a receipt is trusted local acceptance, not a
transferable authorization claim").

## What a mirror must be able to prove

1. **What it holds is what the source published.** The archive revision and a
   checksum over records, which the snapshot manifest already carries, plus the
   per-record signatures the importer already verifies.
2. **That it kept up.** The time of the last successful pull and the revision it
   obtained, so a member can tell a live mirror from a stale one.
3. **What disappeared.** Comparing two successive snapshots shows which records
   were removed between them, and whether a retraction or removal receipt
   accounts for each. A removal with a receipt is moderation. A removal without
   one is rewriting. This is the censorship evidence that Git history provided
   and that history-free downloads no longer give to anyone but the operator.
4. **Where else to look.** Which other mirrors and successors exist. Nothing in
   the record carries this today; it is the successor-discovery piece in
   [the discovery essays](../product/instance_discovery_for_and_against.md).

Point 3 matters doubly once history purge ships: after a purge, the server's
own history no longer proves what was removed either. Mirrors become the only
independent record of removals.

## Options a Step 1 would compare

- **A. Scheduled import on a second instance.** Finish the deferred scheduling
  of `import-instance` through the existing task queue. Reuses the downloader,
  planner, signature checks and conflict reporting. Requires a second running
  v3, so it serves a second steward, not an ordinary member. Gives a mirror
  instance and, with the planned incremental feed, a fork path.
- **B. Archive mirror command.** A `./v3` command that fetches the public
  archive on a schedule, verifies it, stores successive snapshots, and reports
  additions, removals and whether each removal has a receipt. Needs only PHP
  and disk. Serves any member. Produces the independent removal evidence in
  point 3. Does not by itself run a site, but `import-repository` already
  rebuilds one from an archive.
- **C. Cooperative Git mirroring.** The operator pushes the repository,
  including history, to one or more member-held remotes on every write. Cheapest
  and the only option that preserves commit history off-host. Depends entirely
  on operator cooperation, so it covers disaster recovery and succession but
  not a hostile operator. It also conflicts with history purge unless purges
  are pushed too.
- **D. Browser-held copies.** The offline snapshot already keeps up to 50 recent
  threads in a member's browser. Extending it to the whole record is the Nostr
  "every client holds copies" model. Bounded storage, no verification story,
  members-only instances excluded. Not a mirror in any useful sense; listed to
  rule it out.

A likely recommendation is B first, then A's scheduling as its natural
extension, with C documented as an operator runbook rather than a feature.
That is for a Step 1 to argue.

## Boundaries that any option must respect

- Public instances only, or authenticated member access on members-only
  instances. The site gate already covers downloads.
- Public record only. No private stores, no provider credentials, no agent
  signing keys, no sessions. The [portability assessment](instance_portability_gap_assessment.md)
  lists what stays out.
- Retractions still apply. A mirror that imports receipts honors them; the
  importer already refuses to resurrect accepted deletions. A mirror that
  stopped pulling keeps whatever it last saw, and nothing can prevent that.
  Documentation must say so plainly, as the retraction docs already do about
  downloaded copies.
- No new dependency. Fetching, hashing, archiving and diffing are all within
  PHP and Git.
- Plain-text records. Any new record the mirror writes or reads, such as a
  mirror log or a successor list, uses the project's `Key: value` format. The
  existing snapshot manifest is JSON and is noted in the
  [human-readable format checklist](human_readable_formats_checklist.md).

## Open questions

- Should a mirror be visible on the source instance (a "mirrors" list members
  can check), or stay private to whoever runs it? Visibility helps members find
  a successor; it also tells a hostile operator whom to cut off.
- How should removal reports treat the planned history purge? A mirror sees
  the content vanish and a receipt appear; it never sees the purge, because it
  never had history. That is a reason the receipt must stand on its own.
- What does a member do with a mirror archive when the operator vanishes? The
  answer is `import-repository` plus a new approval seed, and it should be a
  runbook before it is a feature.
