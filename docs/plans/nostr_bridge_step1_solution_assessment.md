> **Feature plan:** [Step 1](./nostr_bridge_step1_solution_assessment.md) · [Step 2](./nostr_bridge_step2_feature_description.md) · [Step 3](./nostr_bridge_step3_development_plan.md) · [Step 4](./nostr_bridge_step4_implementation_summary.md)

Cycle: `v3/nostr_bridge`. Background: [the Nostr relay debate and how v3 answers it](../product/nostr_relay_debate_and_v3.md). Sibling list: [Nostr strengths checklist](./nostr_strengths_checklist.md).

Debug mode: enabled

Effort study: `railspike-effort` / `v3/nostr_bridge`; host ledger [nostr_bridge.md](./effort_ledger/nostr_bridge.md). Standing P2 enrollment. New cycle.

Extended estimates: enabled. Each option's figure is that option's whole-cycle projection; they are alternatives, not summed.

Cycle estimate: 0.40

## Original Query

Please write Step 1 for this: The two aren't exclusive: a v3 instance could publish its signed posts as Nostr events, or accept Nostr keys as another identity type, if you wanted the reach without giving up the repo.

[Said in chat, 2026-11-22. The quoted sentence closed Claude's comparison of ZenMemes and Nostr in the same chat.]

## Understood Intent

Give a v3 community some of Nostr's reach. The Git repository stays the record. Two directions are on the table:

- **Out:** posts made on a v3 site also appear on Nostr relays.
- **In:** a person's Nostr key means something on a v3 site.

## Problem

A v3 post is seen only by people who visit that site, and a Nostr user who arrives must start a new identity from nothing.

Concepts touched: [key retirement](../concepts/key_retirement.md) and [replaced key](../concepts/replaced_key.md) (unchanged in A to C; changed in D). [Post retraction](../concepts/post_retraction.md) (changed in B and C: a retraction also asks relays to delete). [Signed post bundle](../concepts/signed_post_bundle.md) (changed in D only). New concept candidates: Nostr key link (A, B), Nostr publication (B, C). Feedback: no matching item in `docs/feedback/items/`.

## Shared by every option

- One feature flag, off by default. An instance that never turns it on sees no change.
- The code ships no relay addresses. The operator names the relays, as the [looking-outward principle](../../PRINCIPLES.md#communities-describe-themselves-instances-look-outward-only-where-their-operator-points-them) requires.
- The Git record stays the source. Anything sent to Nostr is a copy made from it.
- A member's Nostr private key stays with the member, in a Nostr signer such as a browser extension. The server never holds it.
- Only public posts on public instances take part. Private messages and members-only instances are left out.
- No third-party code. PHP has no built-in check for Nostr signatures, so we write that check ourselves. Dependency delta: zero.
- Everything that works without JavaScript today keeps working. The Nostr actions are extras that need JavaScript and a signer.
- New records use the project's `Key: value` text format.

## Option A — Link a Nostr key to a username

**Estimate: 0.40**
Main driver: our own Nostr signature check in PHP, one new record type, the Account control, and the profile line. Uncertainty: medium; the signature check is new ground for this codebase.

A member with a Nostr signer presses one button on Account and approves a prompt in the signer. The site stores a signed record that says "this username also holds this Nostr key". The profile page and the profile's text view show the Nostr key. Nothing is sent to any relay.

- Pro: the smallest slice that makes a Nostr key mean something on v3.
- Pro: the server only checks signatures. It holds no new secret.
- Pro: Options B and D both need this link, so no work is thrown away.
- Con: it adds no reach by itself. The link is shown, not used.
- Con: few people have a signer on iPhone, so the first users will be on desktop.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*

## Option B — Option A, plus a member sends their own post to Nostr

**Estimate: 0.80**
Main driver: Option A plus the note format, a relay list in Tools, the browser code that talks to relays, and what a retraction does. Uncertainty: medium to high; relays differ in what they accept.

After a member with a linked Nostr key posts, the post offers "Send to Nostr". The member's signer signs a note with their own Nostr key. The note carries the post's text, a link back to the thread and the post's ID. The browser sends the note straight to the operator's named relays. The server sends nothing.

- Pro: on Nostr the post belongs to the member's own key, which is what Nostr readers trust.
- Pro: the browser already speaks the relays' protocol, so PHP needs no relay client.
- Pro: sending is the member's own act, post by post, so consent is built in.
- Con: agents posting through `quill.py` and members without a signer are not covered.
- Con: a note on a relay cannot be reliably recalled. A retraction can ask relays to delete it, and a relay may refuse.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*

## Option C — The instance sends every signed public post

**Estimate: 0.90**
Main driver: a Nostr key for the instance, our own signing code and relay client in PHP, a task-queue job, consent settings, and retraction handling. Uncertainty: high.

The instance gets its own Nostr key. A background job sends each signed public post to the named relays, signed by the instance. Each note names the author's username and OpenPGP fingerprint and links to the thread.

- Pro: every post gets reach, agent posts included, with no setup by members and no JavaScript.
- Con: the server holds a new signing secret. It is the instance's key, not a member's, but it is the first of its kind in v3.
- Con: on Nostr every post looks written by the instance. A reader must follow the link to check the real author.
- Con: it sends members' words to a network they did not choose, so each member needs a say first.
- Con: signing and a relay client in PHP are the two largest pieces of new code in any option.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*

## Option D — A Nostr key signs posts directly

**Estimate: 1.60**
Main driver: a second signature format through posting, reactions, approval, key pages, retirement, import, `quill.py` and private messages. Uncertainty: high. Above 1.0, so it would be split into several cycles.

A Nostr key becomes a second kind of signing key beside OpenPGP. A Nostr user registers, gets approved and posts with the key they already have. Each such post is also a valid Nostr event.

- Pro: a Nostr user joins without making a new key.
- Pro: posts need no conversion to appear on Nostr.
- Con: every place that checks a signature changes, and both formats must work forever.
- Con: private messages use OpenPGP encryption, which a Nostr key cannot do, so those members would have no DMs at first.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*

## Recommendation

Option A now, then Option B's sending as the next cycle. Together they give the reach without giving up the repo, and neither puts a secret on the server. Option C can follow as an operator's choice if agent posts need reach too. Option D waits until Nostr users actually ask to join with their own key.

Vertical slice for A: on a desktop browser with a Nostr signer, a member opens Account and presses "Link Nostr key". The signer asks once. The profile page then shows the Nostr key, and the profile's text view shows the link record. A local clone imports the record and accepts it. In a browser with no signer, the button is not shown. With the flag off, nothing is shown anywhere.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*

## Open questions for the maintainer

- **Big-number math on the server.** Our signature check needs PHP's GMP extension to run at a usable speed. Default: the flag stays locked off where GMP is missing. Is GMP present on the ZenMemes host?
- **Who may link.** Default: any key with a username. Alternative: approved members only.
- **Proof in both directions.** Default: the Nostr key signs a statement naming the member's OpenPGP fingerprint, and the member's OpenPGP key signs the record that holds it. Either key alone cannot claim the other.
- **Unlinking.** Default: the member retracts the link record with the existing retraction method.
- **Does a linked Nostr key grant anything?** Default: no. It is shown, and Option B uses it later. Approval stays with OpenPGP keys.
- **For Option B later:** a plain note shows in every Nostr app; a thread-type note matches a v3 thread better but fewer apps show it. Decide in that cycle.

## Enhancement requests

- **Request:** a checklist of things to do to get Nostr's other strengths into v3 (maintainer, 2026-11-22, while this Step 1 was being written). **Disposition and reason:** separate work; it is a list of future cycles, not part of this one. **Approval status:** requested directly. **Outcome:** written as [nostr_strengths_checklist.md](./nostr_strengths_checklist.md); it lists this cycle as its first item.

## Study snapshots and approval

- 2026-11-22 UTC, intake after inspection (`PRINCIPLES.md`, `CONTRIBUTING.md` dependency policy, `nostr_relay_debate_and_v3.md`, the signed post bundle, outbox and key retirement concepts, the `Security/` file list): 0.40 projected total for the recommended scope. No pristine intake. Claude, single exposed rater; R1 hashes not re-verified this session; baseline `origin/main` `22fc9f1`.
- Step 1 handoff: 0.40 for Option A (alternatives B 0.80, C 0.90, D 1.60; not summed). No implementation. Anchors: `v3/zen_hero` 0.35, `v3/key_approval_api` 0.55.
- Not verified: whether GMP is installed on the production host, and current signer support on iPhone.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*
