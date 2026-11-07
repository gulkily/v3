> **Feature plan:** [Step 1](./post_revision_step1_solution_assessment.md) · [Step 2](./post_revision_step2_feature_description.md) · [Step 3](./post_revision_step3_development_plan.md) · [Step 4](./post_revision_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/post_revision`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Ledger entry pending until planning is committed.

Extended estimates: enabled

Cycle estimate: 0.80

## Original Query

Also, let's draft a feature that allows us to replace a post in place, without necessarily deleting the old one.

## Understood Intent

Today an author who wants to fix a post has two choices: retract it, which deletes it and breaks its links, or reply with a corrected copy, which leaves the stale version first in the thread. The request came up when Claude revised its thread on transparent social networks and had to post the new text as a reply. The goal is to let an author put a new version in the old post's place, at the same address, while the earlier text stays in the record unless someone chooses to remove it.

## Problem

A published post cannot be corrected where it stands, so every fix either deletes the post or pushes the correction below the outdated version.

Concepts touched: [post presentation](../../concepts/post_presentation.md) (every option: the post shows its current version); [post body formatting](../../concepts/post_body_formatting.md) (precedent for Option A: a sidecar record changes how a post displays); [thread title](../../concepts/thread_title.md) (precedent for Options A and C); [post retraction](../../concepts/post_retraction.md) (boundary: deleting an earlier version stays a retraction matter); a new candidate concept, **post revision**, in every option. No matching feedback item in `docs/feedback/items/`.

## Shared by every option

- Only the key that signed the post can replace it. Whether an operator may also replace someone else's post is a question for the maintainer; the recommendation assumes not, since that would put words under another person's name.
- The original post file and its signature are never rewritten, so the old version keeps verifying.
- The post keeps its ID and URL, and readers can see that it was revised and open the earlier versions.
- Reactions stay attached to the post. Likes given to the old text then count for the new text, and readers should be able to tell which version a like was given to. Step 2 decides how much of that to show.
- Unsigned and anonymous posts cannot be replaced, because no key can prove the replacement comes from the same person.
- Deleting an earlier version is out of scope. It can follow through retraction later.

## Option A — Revision record, like post formats

**Estimate: 0.80**
Main driver: a new record family with spec, write path, agent API, read-model change and every view that shows a body. Uncertainty: medium, mostly in how many readers take the body straight from the post file.

The author signs a revision record that names the post and carries the full new body. Records live beside the post-format and thread-subject records, and the latest one by the post's author sets the text readers see. This is how post formats and thread titles already change after publication without touching the post.

- Pro: follows two existing precedents for changing a post after it is published.
- Pro: one post, one ID, so links, reactions and reply parents are unaffected.
- Pro: exports and rebuilds carry every version as plain records.
- Con: a new record family, spec and schema change.
- Con: previews, quotes, search, Recent, Inbox and LLM exchanges must all read the current version rather than the post file.

## Option B — Replacement post with a `Replaces:` header

**Estimate: 0.70**
Main driver: a new header in the canonical post record, plus thread, count and notification rules that hide replacements as separate posts. Uncertainty: medium to high.

The author publishes an ordinary post whose record carries `Replaces: <post-id>`. Readers see its body in the old post's place, and the replacement is not listed as a reply of its own.

- Pro: each version is a complete signed post with its own permalink.
- Pro: reuses the existing post write path and composer.
- Con: changes the canonical post record spec.
- Con: reply counts, Inbox, Recent and thread order must all learn to skip replacements, and a replacement in the wrong thread needs a rule.
- Con: two IDs for one post invite links and replies to the wrong version.

## Option C — Visible `replaces:` line, read at display time

**Estimate: 0.45**
Main driver: one directive reader with an authority check on the thread page. Uncertainty: medium.

A reply containing `replaces: <post-id>` is shown in the named post's place when both have the same author, worked out when the page is read, like the `event:` and `theme:` directives.

- Pro: no record or schema change.
- Con: the directive line shows in the replacement's text.
- Con: only the thread page knows about the replacement; previews, Recent and search still show the old text.
- Con: the replacement still exists as a reply, so counts and Inbox show it twice.

## Recommendation

Choose Option A. It is the pattern the project already uses for changing a post after publication, it keeps one ID per post, and it keeps every version in the record without rewriting anything. Option C is cheaper but leaves the old text showing in most places, which defeats the purpose.

Vertical slice: the author of a reply sends a revision through the agent API; the thread page and post page show the new body with a revised marker and a link to earlier versions; llms.txt documents it. The browser composer's edit control, root posts and subjects, and the per-version like display can follow as later slices.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Study checkpoint

2026-11-07T18:00:00Z, `railspike-effort` / `v3/post_revision`, intake and Step 1 handoff. New cycle from the maintainer's request in conversation. Scope v1: Option A, first slice as above. Planning revision `6d432a0c1684d04bf203d79d546c8c4d42c13f72`. Reference R1, protocol P2; frozen R1 hashes not re-verified. Single exposed Claude rater. Before rating, the rater read the post-format and thread-subject record specs, `PostFormatRecordParser.php`, the post retraction and post presentation concepts, and the retraction simplification handoff, so this is not a pristine intake. Intake and Step 1 projected total **0.80**. Option estimates 0.80, 0.70 and 0.45 are alternatives and do not add up. No implementation, measured labor or accepted outcome.

2026-11-07T18:00:00Z, backlogged at the maintainer's request ("let's backlog this please"). Step 1 remains unapproved. Scope v1 and projected total **0.80** retained; the cycle is paused, not abandoned or accepted.

## Status

Backlogged at the maintainer's request on 2026-11-07. Step 1 remains unapproved; no further planning or implementation is authorized. Later navigation targets will exist if subsequent steps are approved.

## Enhancement Requests

None yet.
