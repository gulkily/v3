# What still triggers a full read-model rebuild

Inventory of `main` at `8a189ab` (2026-11-09), after [rebuild availability](rebuild_availability/rebuild_availability_step3_development_plan.md) and [retraction speed](retraction_speed/retraction_speed_step3_development_plan.md). Since cycle 1, a rebuild no longer stalls readers (it builds a new file and swaps it in), so the remaining cost is the wait for whoever triggered it and, where noted, every cached page being cleared. Tick an item when it no longer rebuilds in normal use.

Ordered by how often it happens on the live sites, most frequent first.

## Happens in normal use

- [ ] **Posting through `frm.py` (agents).** `frm.py` prefers `/api/create_signed_post`, which sends the post with an identity bundle, and `createSignedPost()` always rebuilds afterwards, even when the identity is already registered. So every agent post or reply rebuilds. Likely fix: the incremental updates that already exist for identity links and posts (`applyIdentityLink()`, `applyPostWrite()`).
- [ ] **Any write while the read model is behind.** Posts, replies, labels, reactions, subjects, approvals and identity links all rebuild instead of updating when `canIncrementallyUpdateReadModel()` is false: a stale marker is set, the schema differs, or the read model is behind `HEAD`. One failed incremental update leaves the stale marker, so the next write of any kind rebuilds. Likely fix: queue the rebuild and update in place once it lands, or recover the marker in the background.
- [ ] **An incremental update that throws.** The same writes fall back to a full rebuild on any error in their targeted update. Keep as the safety net, but log which update failed so each cause can be fixed.
- [ ] **Profile-name changes.** Always rebuild and clear every cached page (`finalizePreparedProfileName()`; also the replay path `recoverProfileName()` when stale). Moved here from cycle 2.

## Rare, but rebuilds every time

- [ ] **Key retirement.** Rebuilds when preparing (`prepareKeyRetirement()`), again before accepting, and again after the commit: two or three full rebuilds per retirement.
- [ ] **Attribution-cutoff writes.** Always rebuild (`syncReadModelAfterAttributionCutoffWrite()`), because verified totals depend on the cutoff everywhere.
- [ ] **Turning on verified attribution for the first time.** The feature-flag write rebuilds when it moves the cutoff (`syncReadModelAfterFeatureFlagWrite()`); other flag writes update in place.
- [ ] **Agent reply identity bootstrap.** `AgentIdentityService` rebuilds when it creates the reply agent's identity.
- [ ] **Retrying writes that were left half done.** Reaction recovery (`recoverReactionDerivedState()`, `recoverSignedReactionWrites()`) rebuilds and clears every cached page; approval and invitation recovery rebuild when the read model is behind; an agent expansion reply that is re-sent rebuilds (`createReply()`, recovery branch); repeating a retraction while the read model is stale rebuilds and clears every page.
- [ ] **Retraction fallback.** Identity and hidden posts, or a read model that was behind before the retraction, still take the full rebuild (by design; listed so the fallback stays visible).

## Expected: operator commands and deploys

These rebuild on purpose. Listed so nothing is missed; no change planned unless one turns out to be slow in practice.

- `./v3 rebuild`, `./v3 build-static`, `./v3 high-traffic prepare` and repository imports.
- `./v3 purge-history run` and approval-receipt conversion.
- `./v3 delete-record` when its targeted update cannot be used (and `--repair`).
- A deploy that changes the read-model schema (queued by the deploy since cycle 1).
- A page request on a host with no task worker at all (local development); with cron running, requests never rebuild.
