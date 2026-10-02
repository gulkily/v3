# Future Feature Note: Guest Keypair + Proof-of-Work Voting

Not scheduled yet. Captured for later FDP Step 1 when picked up.

## Idea (as described by the user)

- On first page load (any page, no explicit signup step), automatically
  generate a guest keypair for the visitor, client-side.
- Gate that auto-generation behind a short proof-of-work challenge, as
  an anti-abuse cost on mass identity creation.
- Let any identity vote (upvote/downvote) and have it count toward the
  displayed score/vote total immediately, regardless of approval status.
- The visible score should bump on the page right away and persist
  across reloads — immediate, durable feedback is the point ("more
  engaging").

## Why this matters now

- Related to the already-done
  [`anonymous_reaction_scoring_step1_solution_assessment.md`](anonymous_reaction_scoring_step1_solution_assessment.md),
  whose Option C ("lower the bar for approved generally... e.g. on first
  browser-generated identity") is effectively a lighter version of this
  idea. That assessment explicitly deferred it: "it changes shared
  scoring behavior used by every reaction on the site, so it belongs in
  its own plan."
- Also the subject of `qdb_todo.txt` item 3 (anon voting / vote counts
  reading 0).

## Flagged risk: unbounded file/commit growth

- Every reaction write is one canonical file and one git commit,
  forever. The existing per-identity dedup (`hasThreadTagFromIdentity`/
  `hasPostTagFromIdentity`) already caps this at one record per
  (identity, post, tag) — so the real growth driver is unique
  *identities* created over time, not raw click volume. If guest
  keypairs aren't persisted durably per visitor, each repeat visit could
  mint a new identity and a new vote-eligible record, compounding
  without bound.
- "Thousands" of votes is not itself a problem (this app is about to
  import 79k quote files, and the source qdb dump accumulated 2.25M+
  vote rows over 20 years) — the risk is a sustained, unbounded *rate*
  once drive-by voting is unlocked, which grows both write latency and
  full read-model rebuild time over time. Proof-of-work only taxes
  identity creation, not voting from an identity already created.
- Proposed direction (not decided): reuse the same aggregation
  mechanism being built for the archive importer — a periodic
  compaction pass that folds reaction records past some age into the
  post's own aggregate score header (the importer's "baked-in" seed),
  then retires the individual files. Recent activity stays as
  individually auditable records; long-term file/commit count stays
  bounded instead of growing forever. Needs its own design pass
  (compaction cadence, what stays inspectable vs. retired) when this
  feature reaches Step 1.

## Known open questions for its own Step 1

- Auto-creating a keypair per visitor at QDB-style traffic volumes means
  uncapped identity records; does PoW alone bound that acceptably, or
  does it also need rate-limiting/dedup per voter?
- Every reaction write in this app is a real git commit per the existing
  canonical-record model — uncapped anonymous voting means uncapped
  commits. PoW raises the cost of forging a *new identity*, but doesn't
  by itself cap vote volume from one already-created guest identity.
- Where does guest keypair generation/storage live client-side (browser
  storage, durability across devices), and how does that interact with
  the existing OpenPGP identity-bootstrap/approval flow already in the
  write path?
- Scope: does this apply site-wide (all instances: zenmemes, chouse,
  qdb) or only to the qdb instance, where the classic-QDB drive-by-vote
  feel is the explicit motivation?

## Sequencing decision

Build **after** the QDB archive importer (see
`qdb_archive_importer_step1_solution_assessment.md`). The importer's
chosen approach (aggregate imported scores, no live anonymous-vote
replay) has no dependency on this feature, and this feature is a
larger, shared-infrastructure change that deserves its own full FDP
cycle rather than riding in on the importer's scope.
