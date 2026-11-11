# Quiet composer bottom

Cycle: `v3/quiet_composer_bottom`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: disabled

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)

Extended estimates: disabled (one approach, one file)

**Cycle estimate: 0.20 (intake)**

Status: implemented and verified locally; PR open, awaiting maintainer review, merge and deploy.

## Original request

Thread: [the default new post field is still fucking ugly...](https://zenmemes.com/threads/thread-20261110165107-01f2d2e0), posted by ilyag (maintainer) on 2026-11-10 with a desktop screenshot, plus a reply on 2026-11-11 with an iPhone screenshot ("Also bad on iPhone"). Summary: the bottom of the New post form is too busy. Feedback item: PF-20261111-F02.

## Authorization

Maintainer, 2026-11-11, pasted the thread link into a Claude session with no other text. Under `docs/agents/thread_to_pr.md` this authorizes one-shot execution of the bounded outcome, pushing the branch, opening the PR and replying in the thread. It does not authorize merging, deploying or production access.

## Outcome and work type

Application change (CSS only). On the full composer, below Post: Title becomes an underlined line instead of a second text box, and the closed Tags, Event and Options tools become one row of small muted words with their chevrons. A tool gets its box back when opened. DOM order, behavior and 44px tap targets are unchanged; reply and compact composers are untouched (their Options row is not inside `.compose-tools`).

## Approach and risks

Considered: (a) restyle in place, CSS only; (b) move Tags/Event/Options into the ··· menu; (c) put Title above the body. Chose (a): smallest and reversible, keeps every tool one tap away and keeps the body-then-Post order that lets Post fit above a phone keyboard (`compose_layout_stability.mjs`). (b) hides tags, which most posts use; (c) breaks that phone fit. Risk: theme rules for inputs and `.compose-disclosure` (Gloss rounds them into pills); the new rules use `:root` to sit above them, checked in Gloss, Gloss Dark, Light and Word 97.

## Reuse decisions

Existing markup in `templates/partials/composer.php` and `.compose-disclosure` rules in `public/assets/compose.css`, restyled only for the closed state inside `.compose-tools`. No new components or dependencies.

## Completion conditions

- Normal entry: New post in the nav (`/compose/thread`).
- End-to-end: below Post, a line for Title and one row of Tags · #general, Event, Options; opening one shows its fields in a box.
- Release: PR merged and deployed by the maintainer.

## Verification

Smoke set (445 passed); `tests/browser/composers.mjs`, `compose_disclosure.mjs`, `event_when.mjs`, `compose_layout_stability.mjs --phone-fit-only` pass. Screenshots before and after in headless Chrome. Full suite not run, per `AGENTS.md`.

Starting revision and branch: `fix/quiet-composer-bottom` from `f13dd20f`.

## History

- 2026-11-11: intake, implemented, verified locally; commit reference pending.
