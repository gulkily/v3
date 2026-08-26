# Anonymous Reply Vouching Options Plan

## Goal

Let approved users vouch for anonymous replies without pretending that the
anonymous author has become an approved identity.

The feature should answer two different questions cleanly:

1. Is this anonymous reply worth trusting or keeping visible?
2. Has a known identity later claimed authorship of this anonymous reply?

V1 should focus on the first question. The second question is useful, but it
requires stronger proof and should be implemented as a separate claim flow.

## Current Context

The current approval system is identity-based:

- approved status is derived from canonical approval seed records and structured
  approval replies
- approval replies approve an `openpgp:<fingerprint>` identity
- valid approval requires the approving post to have an approved
  `Author-Identity-ID`
- anonymous posts have no `Author-Identity-ID`
- anonymous authors render as `guest` and cannot participate in identity
  approval chains

This means an anonymous reply cannot be approved with the existing user-vouching
mechanism because there is no target identity to approve.

The repo also has post-reaction records:

- `records/post-reactions/`
- target a `Post-ID`
- carry `Author-Identity-ID`
- already reduce approved-user reactions differently from unapproved reactions

That makes post-level vouching the lowest-risk fit for anonymous replies.

## Options

### Option A: Post-Level Vouch Reaction

Add a canonical post-reaction tag such as `vouch` or `trusted` against the
anonymous reply.

Example:

```text
Record-ID: post-reaction-20260609120000-ab12cd34
Created-At: 2026-06-09T12:00:00Z
Post-ID: reply-001
Operation: add
Tags: vouch
Author-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954
Reason: I know this reply was made in good faith

```

Semantics:

- only approved users' `vouch` reactions count
- vouching changes trust/visibility metadata for the reply
- vouching does not approve the anonymous author
- vouching does not create an identity approval chain
- multiple approved vouches can accumulate

Pros:

- reuses the post-reaction record family
- works for truly anonymous content
- preserves the distinction between content trust and user approval
- can be shown directly on the reply card

Cons:

- does not help the anonymous author build reputation
- needs UI language that avoids implying identity verification

### Option B: Anonymous Reply Claim Flow

Let a user with a browser identity claim a previous anonymous reply.

Possible canonical shape:

```text
Record-ID: anonymous-claim-20260609120500-ab12cd34
Created-At: 2026-06-09T12:05:00Z
Post-ID: reply-001
Claimant-Identity-ID: openpgp:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
Operation: claim

```

The stronger version requires a signed browser proof that binds:

- target `Post-ID`
- post content hash
- claimant identity
- created time

Semantics:

- claim is separate from vouching
- a claim can show "claimed by Alice" after verification
- an approved user's later approval can approve the claimant identity through
  the existing profile approval system

Pros:

- lets an author regain attribution for an anonymous post
- can help reputation after the fact

Cons:

- hard to prove if the original anonymous post had no hidden nonce or signature
- unsafe if anyone can claim old anonymous content without evidence
- probably needs a new canonical record family and UI review workflow

### Option C: Moderator Conversion

Let an approved operator rewrite or annotate an anonymous reply as trusted,
possibly by adding a hidden moderation record.

Pros:

- simple operationally
- useful for moderation workflows

Cons:

- too centralized for the existing social-vouching model
- less transparent than a normal approved-user reaction
- risks conflating moderator action with community vouching

### Option D: Require Identity For Vouchable Replies Going Forward

Keep anonymous replies anonymous but add an optional anonymous signing token or
ephemeral browser-held proof at submission time.

Pros:

- supports later claim proofs
- can preserve public anonymity while retaining private author continuity

Cons:

- does not help existing anonymous replies
- adds complexity to compose, storage, and privacy messaging
- easy to get wrong from a privacy perspective

## Recommendation

Implement Option A first: post-level vouch reactions.

This matches the current data model because the target is a post, not an
identity. It also avoids a bad trust shortcut: an approved user can vouch that a
specific anonymous reply is valuable or acceptable, but cannot approve an
unknown anonymous author.

Treat Option B as a later feature only after the product decides how strong the
claim proof must be.

## V1 Product Semantics

An approved user can vouch for any visible anonymous reply.

A reply is vouchable when:

- it is a reply or thread root with `author_identity_id IS NULL`
- it is not hidden
- the viewer has an approved profile
- the viewer has not already vouched for that post

A vouch means:

- "An approved community member stands behind this anonymous reply."
- not "This anonymous author is approved."
- not "The voucher wrote this reply."
- not "The reply is immune from future flags or moderation."

Vouching is append-only in V1. Revoking a vouch is out of scope.

## Canonical Contract

Reuse `records/post-reactions/`.

Add a `vouch` tag to `Post Reaction Record V1`.

Valid vouch records:

- `Operation: add`
- `Tags` contains `vouch`
- `Post-ID` targets an existing post
- `Author-Identity-ID` is present
- the author identity is approved at the point the read model derives reaction
  state

Invalid or non-counting vouch records:

- missing `Author-Identity-ID`
- author identity is unapproved
- target post does not exist
- target post is authored by an identity if V1 is limited to anonymous content
- duplicate vouch by the same approved identity for the same post

Duplicate vouches by one approved identity should count once.

## Read Model Changes

Extend post reaction reduction to derive:

- `approved_vouch_count`
- `approved_voucher_ids_json`
- optionally `viewer_has_vouched` in page models, not as stored global state

Add display-level derived fields:

- `is_vouched_anonymous_reply`
- `vouch_label`, for example `Vouched for by 2 approved users`

Keep `author_is_approved = 0` for anonymous posts. Do not let vouches mutate
the author fields.

Suggested schema migration:

- add `approved_vouch_count INTEGER NOT NULL DEFAULT 0` to `posts`
- avoid storing voucher lists if the table is meant to stay compact; compute
  viewer state from reaction records when rendering

If viewer-specific vouch state is needed efficiently, add a helper query rather
than embedding it into static read-model rows.

## UI Changes

On anonymous reply cards:

- show a compact trust label when `approved_vouch_count > 0`
- show a `Vouch` action only for approved viewers
- hide the action after that viewer has already vouched
- show a clear result message after the action

Suggested label:

```text
Vouched for by approved users
```

If counts are shown:

```text
Vouched for by 1 approved user
Vouched for by 3 approved users
```

Avoid labels like `approved anonymous user`, `verified author`, or `trusted
author`; those overstate what the vouch proves.

## API Changes

Add a dedicated endpoint:

```text
POST /api/vouch_post
```

Request:

```json
{
  "post_id": "reply-001"
}
```

Authorization:

- resolve viewer from `identity_hint`
- require approved viewer profile
- reject unapproved or anonymous viewers with `403`

Validation:

- target post exists
- target post is visible
- target post has no `author_identity_id`
- viewer has not already vouched for the target post

Write behavior:

- create a `post-reactions` record with tag `vouch`
- use the viewer identity as `Author-Identity-ID`
- rebuild or incrementally refresh affected read-model fields
- invalidate affected post/thread/static artifacts

Response:

```json
{
  "status": "ok",
  "post_id": "reply-001",
  "approved_vouch_count": 1,
  "viewer_has_vouched": true
}
```

## Relationship To Flags And Scores

Vouching should not cancel flags in V1.

Keep separate fields:

- flags indicate concern or moderation pressure
- vouches indicate approved-user support
- score can include both later if product wants a combined ranking

If the UI needs a single visible status, keep it explainable:

- show both `Flagged` and `Vouched` states if both exist
- do not hide approved flags merely because a vouch exists

## Optional Claim Flow Later

If the community wants anonymous authors to reclaim credit, add a separate
claim mechanism after V1.

Recommended future contract:

- new record family under `records/post-claims/`
- claim references `Post-ID`
- claim includes `Claimant-Identity-ID`
- claim includes the post content hash
- claim body includes a browser signature over the claim payload
- read model exposes `claimed_by_profile_slug` only after signature validation

Open problem:

- existing anonymous posts cannot be strongly claimed unless there is some
  secret, signature, or independent moderator review proving authorship.

For that reason, do not block V1 post-level vouching on claims.

## Implementation Slices

### Slice 1: Canonical And Parser Contract

- update `docs/specs/post_reaction_record_v1.md` to define `vouch`
- ensure tag validation accepts `vouch`
- add parser/repository tests for vouch reaction records
- document duplicate-vouch collapse semantics

### Slice 2: Write Endpoint

- add `POST /api/vouch_post`
- require approved viewer identity
- validate anonymous visible target post
- write a canonical post-reaction record
- return deterministic JSON responses

### Slice 3: Read Model Derivation

- derive approved vouch counts from approved identities only
- count one vouch per approved identity per post
- keep anonymous author approval fields unchanged
- add rebuild and incremental update coverage

### Slice 4: UI

- render the vouch label on anonymous vouched replies
- render the action for eligible approved viewers
- add browser JS for in-place submission and feedback
- keep static/no-JS behavior acceptable if the existing pattern supports it

### Slice 5: Tests And Hardening

- approved viewer can vouch for anonymous reply
- unapproved viewer cannot vouch
- anonymous viewer cannot vouch
- duplicate vouch by one identity counts once or is rejected deterministically
- vouch does not approve the anonymous author
- vouch does not count from unapproved identities
- vouch on identified post is rejected in V1
- hidden post cannot be vouched
- rebuild and incremental refresh produce the same counts

## Open Questions

- Should vouching apply to anonymous thread roots as well as anonymous replies?
- Should approved users be able to vouch for identified but unapproved users'
  posts, or should that remain identity approval only?
- Should the UI show voucher names, counts only, or both?
- Should a vouch have an optional reason visible to other approved users?
- Should there be a later `unvouch` or `revoke-vouch` event?
