# Agent API Onboarding Checklist

Source: an agent's review of zenmemes.com (12 documentation suggestions plus one
security finding), checked against the code on 2026-10-20. Each cycle below is
one Railspike cycle; give each its own feature prefix when it starts.

Cycle estimates use the Railspike effort scale (1.0 = the largest slice we
would comfortably approve for one cycle).

| Cycle | Scope | Review items | Estimate |
| --- | --- | --- | --- |
| A | Stop unsigned routes from attributing posts to identities | Security finding | 1.0 |
| B | Idempotent signed retries and actionable write errors | 6 (errors), 10 | 0.6 |
| C | Author and signature details in the read API | 9 | 0.5 |
| D | Agent protocol docs, discovery, and tested worked example | 1-8, 11, 12 | 1.2 |

Order: A first, because D advertises the API. B and C can run in either order.
D comes last so it documents final behavior. D is close to the 1.0 limit; if
Step 2 runs long, split the worked example into its own cycle (0.4).

## Corrections to the review

- **The security problem is worse than reported.** `resolveAuthorIdentityId()`
  only checks that the identity file exists. Besides displaying the post under
  the victim's name, the read model links it to their profile and post counts,
  and `#label` tags in an unsigned body are written as thread-label records.
  When the victim is approved, those labels count as approved votes toward the
  thread score. `create_quote` has the same problem.
- **Item 11 is partly done already.** The About page already links `/api/` and
  `/llms.txt` in every site profile. Adding the same links to the Account and
  compose pages would go against the AGENTS.md UI copy rule. Use invisible
  discovery instead: `<link rel="alternate">` in the page head, an `/llms.txt`
  link from `/api/`, and an entry in the `/docs` catalog.
- **Item 9 is partly done already.** `get_post` already returns `Author:`
  (a label only). HTML post pages already link the signature through
  `/source/current/<record_path>.asc`, so signatures are already public. The
  status can be computed when the page is requested, using the same logic
  as `ActivityService::sourceSignatureStatus()`, so no read-model schema
  change is needed.
- **Item 10 has the wrong error message.** A clean text-post retry hits
  `Prepared post not found.`, because the prepare token is deleted after a
  successful post. `Prepared post target already exists.` appears only when
  the request failed after the commit but before the token was deleted (for
  example, a derived-state refresh failure or a timeout). The fix has to cover
  both cases. Image posts already have an idempotent retry path that can serve
  as the model.
- **Item 6 depends on the instance.** The ASCII rule applies only when
  `FORUM_UNICODE_AUTHORED_TEXT` is off. The conversion table currently exists
  only in `public/assets/browser_signing.js`, and there is no PHP copy.
  Documentation must say this varies by instance, so instance-specific facts
  belong in generated `/llms.txt`, not in static docs.
- **Item 5 is incomplete.** The UID-derived name is only the starting
  username. Signed renames (`prepare_profile_name`/`finalize_profile_name`,
  behind `FORUM_USERNAME_CHANGES_ENABLED`) can change it. Usernames are not
  unique: duplicate names follow the existing duplicate-name routing, and
  unapproved profiles with the same name are shown separately. The rule of
  one identity per fingerprint is accurate.
- **Item 8 is incomplete.** Instances can run in members-only mode, which
  limits posting to approved identities. Approval happens when an approved
  member vouches for someone, or through an invitation. Images also require
  `FORUM_IMAGE_CONTENT_ENABLED`.
- **Item 12 is your decision.** No rate limiting exists in the code, so the
  docs must not invent limits. Whether agents are welcome and whether they
  must be labeled is the operator's decision. A note about sandbox network
  allowlists is optional.

## Cycle A: unsigned attribution (Step 1 recommended)

- [ ] Audit zenmemes content for unsigned posts and thread-label records that
  carry an `Author-Identity-ID`. Distinguish them from unsigned bootstrap anchors.
- [ ] Decide in Step 1: should unsigned routes reject `author_identity_id` or
  drop it silently? Confirm whether the browser's unsigned fallback
  (`submitUnsignedReplyFormToApi`) ever sends a filled value.
- [ ] Decide how to treat unsigned records that already carry an author:
  leave them as they are, show them as unattributed, or stop counting their
  votes. Any read-model change must follow CONTRIBUTING.md.
- [ ] Cover `create_thread`, `create_reply`, `create_quote`, and the
  thread-label records written from unsigned bodies.
- [ ] Add regression tests showing that a forged post neither links to the
  profile nor counts as a vote.

## Cycle B: retries and errors

- [ ] Make `create_prepared_post` return the original IDs when the committed
  record and signature match, whether or not the token was already used.
- [ ] Decide whether `create_identity` needs the same retry handling
  (it currently returns `Identity already exists for this fingerprint.`).
- [ ] Make the ASCII error name the character, its code point, and its
  location (field, line, column). Decide in Step 1 whether `prepare_*` should
  also apply the browser's conversions, which would require a shared table to
  avoid drift.
- [ ] Keep the text error format of `create_thread` unchanged for existing
  clients.

## Cycle C: verification in the read API

- [ ] Add identity ID, username, signature status, and a signature link to
  each post in `get_thread`, and to `get_post`.
- [ ] Compute these values when the page is requested; do not change the
  read-model schema.
- [ ] Extend the API tests for signed, unsigned, and invalid posts.

## Cycle D: agent docs and discovery

- [ ] Generate `/llms.txt` with the six-step signed flow, links, and current
  instance facts: Unicode/ASCII mode, members-only mode, images, username
  changes.
- [ ] Complete the `/api/` index and mark each route as signed or unsigned.
  Recommend prepare-then-create for posting as an identity.
- [ ] Add `docs/reference/agent_api.md` and list it in `PlatformDocsCatalog`.
  Cover fields, responses (JSON or key=value text), accepted encodings
  (query, form, JSON), signing rules (exact bytes, detached armored signature,
  accepted key types, 10-minute token), usernames, approval, retries, and the
  main errors.
- [ ] Add a gpg+curl worked example script with sample responses, and run it
  against a local instance in tests so it stays correct.
- [ ] Add a `<link rel="alternate">` for discovery; do not add visible copy
  to the Account or compose pages.
- [ ] Publish the agent policy once the operator decides on it.
