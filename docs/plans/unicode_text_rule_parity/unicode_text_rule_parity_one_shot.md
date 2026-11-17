# Unicode text rule parity (server vs browser)

Cycle: `v3/unicode_text_rule_parity`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled (project marker `.railspike_debug_on` present; no explicit request)

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)

Extended estimates: enabled (new enrolled cycle default); no real alternatives or work units, so no unit estimates

**Cycle estimate: 0.25 (intake 0.25; execution entry 0.25; retrospective 0.20)**

Status: technically complete; PR open; Unicode version drift decision and maintainer acceptance pending.

## Original request

On 2026-11-17 the maintainer wrote: "Next suggested work: the checklist's "pairs that must agree" item, starting with whether the server UnicodeTextPolicy and the browser compose-time validator still accept and reject the same text."

## Authorization

The request authorizes a one-shot cycle for checklist §2.2 "Server/browser rule pairs that must agree", first pair only: `UnicodeTextPolicy` and the compose-time JavaScript validator. Exclusions: the incremental updater vs full rebuild pair, and any change to which characters the server accepts. If the two sides disagree and the right answer is a product choice, the cycle records it and asks the maintainer instead of picking a side.

## What the comparison found (2026-11-17, before any change)

The two sides are hand-synced twins, not generated from one source. `UnicodeTextPolicy::assertVisibleCharacter` and `isAllowedVisibleUnicodeCharacter` in `public/assets/browser_signing.js` apply the same checks in the same order, with the same emoji base ranges.

A throwaway scan, kept outside the repository, ran `a<c>b` for every code point through both sides in all three modes: ASCII, Unicode, and Unicode with emoji. The browser was never stricter than the server. Every text the browser rewrites (NFC, typographic punctuation, spaces) is accepted by the server. Two gaps remain where the browser lets text through and the server then rejects it:

1. **ASCII mode lets 30 control characters through.** `unsupportedComposeCharacters` and the removal filter accept `U+0000`–`U+007F`. The server accepts only LF and `U+0020`–`U+007E`. Tab and CR are already rewritten, so the gap is the other C0 controls and DEL. Pasted IRC logs carry exactly these: colour `U+0003`, bold `U+0002`, underline `U+001F`, reset `U+000F`. Today the browser keeps them, and the server answers "body must be ASCII only." `git log -S` shows the range came from the first ASCII helper (`ae3843b8`); its plan says only "keep canonical records ASCII-only", so it was never a deliberate choice. **Fix in this cycle**: the browser uses the server's range.
2. **Unicode version drift: about 15,000 code points.** The sandbox's PHP uses PCRE2 10.42, which knows Unicode 14. Node 22 knows Unicode 17, and current browsers are similar. Characters added since Unicode 14 are unassigned (`Cn`) to the server, so it rejects them as "control or format characters". Examples: emoji from 2022 on, such as 🫨 `U+1FAE8` and 🪿 `U+1FABF`, and new scripts and CJK extensions. The code on both sides is the same; the gap comes from the runtime. **Not fixed here**: whether to accept, explain or close the gap is a product choice. It goes to the maintainer as a decision.

Two smaller differences, recorded and left alone: the server trims outer ASCII whitespace, including `U+000B` and `U+0000`, before checking, and the browser does not. Without the `intl` extension, the server rejects every combining mark, and the browser does not.

## Outcome and work type

Application change plus documentation.

- `public/assets/browser_signing.js`: ASCII mode flags and removes the same characters the server rejects.
- New `tests/UnicodeTextRuleParityTest.php`, registered in `tests/run.php`. It runs one corpus through both sides in all three modes. It asserts that the browser never blocks text the server accepts, and that the server accepts whatever the browser submits, with and without removal. The corpus uses only characters assigned by Unicode 14, so the result does not depend on the PCRE or ICU version.
- Findings, checklist and handoff: record the result and the open decision.
- ZenMemes thread: post the result and the drift decision as a reply.

## Approach and risks

- Risk: a stricter browser blocks text some site needs. Impact is low, because the server already rejects these characters on every path. The parity test checks that the browser never blocks what the server accepts.
- Risk: other fields share the ASCII helper, such as the username field. Every server ASCII check accepts at most LF and printable ASCII, so the browser can only move closer to the server.
- Risk: the test depends on the runtime's Unicode tables. Mitigation: the corpus keeps to Unicode 14 characters and to unassigned code points that stay unassigned.

## Reuse decisions

- Reuse the Node harness in `tests/BrowserSigningNormalizationTest.php`, which loads `browser_signing.js` in a `vm` and calls `window.__forumComposeNormalization.normalizeComposeText`. The parity test needs both sides, so it gets its own file, not more cases in a 8,890-line test.
- Server side: call `UnicodeTextPolicy::normalizeBody` for Unicode modes. For ASCII mode, call `TextValidationException::ascii` after the same CRLF and trim steps as `LocalWriteService::normalizeAsciiBody`, which is private. A comment in the test names it.
- One shared ASCII check in the browser file, used by both the report and the removal filter, replacing the two inline regexes.

## Completion conditions

- The parity test fails on the base commit for ASCII mode and passes after the fix.
- Existing normalization tests still pass.
- Findings and checklist §2.2 record the first pair; the handoff lists the drift decision as open.
- A PR is open, and the thread has a reply signed as "claude".

## Concepts touched

None. The compose normalization is an existing helper.

## Verification and recovery plan

`./v3 test --smoke UnicodeTextRuleParityTest BrowserSigningNormalizationTest UnicodeTextPolicyTest TestFileRegistrationTest`; also run the parity test on the base JavaScript to show it catches the gap. The full suite is not run; the maintainer runs it. Browser check: the change is to a pure function covered by the Node harness, so no page renders differently; UI and theme checks do not apply. Recovery: revert the PR.

Starting revisions and branch: `origin/main` at `6bdc52d7`; branch `quality/unicode_text_rule_parity`.

Planning commit: `8e3cee7f` (confirmed with Git before any implementation edit).

## Dated execution history

- 2026-11-17: wrote `UnicodeTextRuleParityTest` first and ran it on the unchanged browser code. ASCII mode failed on the 30 control characters and the IRC colour sample; both Unicode modes passed.
- 2026-11-17: added `isAllowedAsciiCharacter` to `browser_signing.js`, used by both the report and the removal filter. Added twin notes at `UnicodeTextPolicy` and `isAllowedVisibleUnicodeCharacter`, per the checklist's intentional-duplicate rule. This is an in-scope addition, not a new request.
- 2026-11-17: reran the exhaustive scan against the fixed code. ASCII mode now has 0 differences in either direction.
- No enhancement requests during the cycle.

## Outcome

Delivered: the ASCII control gap is fixed, a parity test guards all three modes, and the findings, checklist and handoff record the result. Not delivered, by design: anything about Unicode version drift. That decision is posted in the ZenMemes thread.

## Verification

- `./v3 test --smoke UnicodeTextRuleParityTest BrowserSigningNormalizationTest UnicodeTextPolicyTest TestFileRegistrationTest`: 586 run, 586 passed. `TestFileRegistrationTest` confirms the new file is registered.
- `UnicodeTextRuleParityTest` on the base browser code: 1 of 3 failed (ASCII mode), as expected.
- Exhaustive scan after the fix: ASCII 0 differences; Unicode and emoji modes differ only on characters newer than Unicode 14 (the drift decision).
- Full suite not run; the maintainer runs it. No UI renders differently, so theme and page checks do not apply. Production's PCRE version was not checked; the drift numbers come from this sandbox's PHP 8.3.6 (PCRE2 10.42) and Node 22.

## Final review

The diff touches one browser helper, one docblock, one new test and its registration, and the review docs. No duplicated component: the ASCII check is now one function where there were two regexes. No server rule changed.

Commit/merge/publication status: completion commit on `quality/unicode_text_rule_parity`; PR open; not merged.

## Study history

- 2026-11-17 intake: **0.25**, scope v1, baseline `6bdc52d7`. Recorded after reading only the review docs, before inspecting either rule set. Drivers: find both implementations, compare them with real test inputs, record the result in the findings; a mismatch fix and a parity test would push it toward the top. Single exposed rater Claude (claude-opus-5-5); R1 hashes not re-verified.
- 2026-11-17 execution entry: **0.25 reaffirmed**, scope v1. The scan found one small fix and one maintainer decision, which the intake drivers already covered.

- 2026-11-17 closeout: delivered scope v1 unchanged, plus the twin notes. Retrospective scope assessment **0.20**: the scan made the comparison quick, and the fix was two lines. Maintainer comfortable-fit judgment pending.

_Claude (Opus 5.5), 2026-11-17_
