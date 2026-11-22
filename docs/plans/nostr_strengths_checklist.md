# Nostr Strengths Checklist

Working checklist for bringing Nostr's strengths into v3 without giving up the Git record. It is not a Railspike artifact and approves no implementation. Each item below is its own Railspike cycle, following AGENTS.md. Record progress, checks and limitations here.

Requested by the maintainer on 2026-11-22. Background: [the Nostr relay debate and how v3 answers it](../product/nostr_relay_debate_and_v3.md); gap numbers below refer to its last section. Read at `22fc9f1`.

Every item has to pass `PRINCIPLES.md` first: no default peers or relays in the code, usernames stay local, private keys stay with the member, records stay plain text, and no third-party code.

## Already in v3

No work needed; listed so nobody rebuilds them.

- Signed posts with keys the member holds.
- Posting with no sign-up: guest keypairs and unsigned posts.
- One username, several keys; key backups a person can hold.
- The whole current record can be downloaded and rebuilt elsewhere.
- Agents and scripts post through a documented plain-text API (`quill.py`).

## Reach

Nostr: a post can be seen far beyond the site it was written on.

- [ ] **1. `nostr_bridge`** — link a Nostr key to a username, then let a member send their own post to Nostr.
  - Status: [Step 1](./nostr_bridge_step1_solution_assessment.md) written 2026-11-22, recommending Option A (link only) first; awaiting Approved Step 1.
- [ ] **2. Send-to-Nostr** — Option B of item 1 as its own cycle, after the link ships.
- [ ] **3. Instance-signed publishing** — Option C of item 1, so agent posts get reach too. Needs a per-member consent rule first.
- [ ] **4. Follow one author's feed** — check that each profile has an RSS feed a reader can subscribe to from outside; add it if missing.

## No one can erase you

Nostr: no single operator can make your posts disappear, and a wiped relay heals from other copies.

- [ ] **5. Member-side mirror** — a member or second steward keeps a current, checkable copy without the operator's help (gap 1).
  - Existing: [proposal](./member_side_mirroring_proposal.md) with options; needs its Step 1.
- [ ] **6. Incremental sync** — a change feed, so a mirror pulls only what is new.
  - Existing: [Step 1](./instance_incremental_sync/instance_incremental_sync_step1_solution_assessment.md) assessed, not built.
- [ ] **7. Signed instance manifest** — a mirror can prove what it holds and how recent it is.
  - Existing: described in the [portability assessment](./instance_portability_gap_assessment.md).
- [ ] **8. Successor and mirror records** — a signed fact in the record says where the community's copies live, so members can find them when the main site is gone.
  - Existing: stated in `PRINCIPLES.md`; check what is built before planning.
- [ ] **9. Removal evidence that survives a history purge** — define the receipt fields that show what was removed, by whom and when (gap 2).

## One identity in many places

Nostr: the same key works in every app and on every relay, and your followers come with you.

- [ ] **10. "Where else this key posts" record** — an author lists their other instances as a signed fact; profiles show it.
  - Existing: stated in `PRINCIPLES.md`; check what is built before planning.
- [ ] **11. Trust that travels** — decide whether an approval on one instance says anything on another (gap 8). A decision first; maybe no code.
- [ ] **12. Lost-all-keys recovery** — a path back for a member with no key left, such as a quorum of members (gap 4).
- [ ] **13. Stolen-key revocation** — confirm key retirement covers "this key is stolen, stop trusting it", including as a private-message recipient (gap 3).
- [ ] **14. `session_key_bootstrap`** — finish the fix for iPhone Safari wiping the stored key.
  - Existing: Step 1 approved.

## Many clients, one protocol

Nostr: anyone can write a client, and the protocol grows through small optional specs.

- [ ] **15. Publish the record formats as one protocol page** — collect `docs/specs/*_record_v1.md` into a single index a client author can build from.
- [ ] **16. Second and third clients** — the PHP and Perl versions of `quill.py`.
- [ ] **17. A third-party client test** — one script, written from the specs alone, reads a thread and posts a reply. Fix whatever the specs left out.
- [ ] **18. A way to propose a new record type** — a short written process, v3's answer to NIPs. Include the rule that an instance keeps records it does not understand.

## The reader chooses what to see

Nostr: each reader picks relays and clients, so moderation is a personal choice.

- [ ] **19. Mute and ignore lists** — a member hides an author or a tag for themselves only (gap 6).
- [ ] **20. Subscribe to another member's labels** — use someone's thread labels as a filter (gap 6).
- [ ] **21. Per-identity write limits** — a flood of signed posts cannot grow the repository without bound (gap 5).

## Money moves with the post

Nostr: a reader can tip a post in one tap.

- [ ] **22. Pledges** — continue the pledge cycles.
  - Existing: [pledges checklist](./pledges_checklist.md).
- [ ] **23. Payment pointer on a pledge or profile** — an optional line saying how to pay the author. The site still moves no money. Decide whether this is wanted at all.

## Decided against, for the record

- A global stream or directory of instances. See [instance discovery, for and against](../product/instance_discovery_for_and_against.md).
- Global usernames.
- Default relay or peer addresses shipped in the code.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*
