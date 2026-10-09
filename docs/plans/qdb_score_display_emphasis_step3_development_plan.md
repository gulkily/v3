# QDB Score Display Emphasis — Step 3: Development Plan

> **Feature plan:** [Step 1](./qdb_score_display_emphasis_step1_solution_assessment.md) · [Step 2](./qdb_score_display_emphasis_step2_feature_description.md) · [Step 3](./qdb_score_display_emphasis_step3_development_plan.md) · [Step 4](./qdb_score_display_emphasis_step4_implementation_summary.md)

## Completion Contract

- Normal entry: a reader opens a QDB quote from a listing or its permalink.
- End-to-end outcome: each retains `(score/votes)`, with only the score number
  bold and sign-colored before and after an in-place vote refresh.
- Required recovery: a failed vote restores the prior structured ratio and
  continues to show the existing reaction feedback.
- Deployment/external verification: no migration or external-service work;
  publish the normal fingerprinted assets and verify the selected application
  tests before release.
- Release condition: QDB initial rendering and live updates meet the approved
  presentation contract, with non-QDB score output unchanged.

## Key Risks

- **High risk: live score refresh can replace the structured ratio.** Impact:
  score-only styling disappears after a vote. Early validation: focused
  browser-behavior coverage. Mitigation: preserve the existing score-node
  contract while making its value and vote-count targets refreshable.
- **Risk: listing and permalink markup can drift.** Impact: inconsistent
  QDB entry points. Early validation: render both surfaces. Mitigation: use
  the same score presentation contract and assertions on both.
- **Risk: theme selectors affect other profiles.** Impact: generic scores
  change. Early validation: scope review and non-QDB rendering test.
  Mitigation: retain QDB-rooted selectors.

## Stage 1

- Goal: render the approved score-only emphasis on every QDB quote surface.
- Dependencies: approved Step 2; current QDB listing and permalink templates.
- Expected changes: retain the full ratio as the reaction score node; give its
  numeric score, vote count, and surrounding ratio text distinct presentation
  targets in both existing QDB quote renderers; apply QDB-scoped bold and
  sign-color rules only to the numeric score.
- Verification approach: render positive, negative, and zero-score QDB
  listings and permalinks; confirm the full text stays `(score/votes)` and
  only the number receives emphasis.
- Risks or open questions:
  - Impact: separate template edits can produce inconsistent markup.
  - Early warning / validation: compare their score-node contract directly.
  - Mitigation: use identical target names and ratio order in both.
- Canonical components/API contracts touched: QDB quote-card score markup,
  QDB permalink root-card score markup, and the `data-role="thread-score"`
  presentation contract.

## Stage 2

- Goal: retain the score-only presentation during optimistic, confirmed, and
  restored thread-reaction states.
- Dependencies: Stage 1's stable score presentation targets.
- Expected changes: extend the thread-reaction score formatter/updater to
  update QDB score and vote-count targets without replacing the ratio node;
  retain existing generic score formats and failed-action restoration.
- Verification approach: exercise a QDB score refresh and an error rollback;
  assert ratio text, selective emphasis targets, and existing generic
  score-update behavior.
- Risks or open questions:
  - Impact: an update path may fail with older/non-QDB score nodes.
  - Early warning / validation: run existing reaction behavior coverage.
  - Mitigation: preserve the current whole-text behavior when the QDB
    presentation targets are absent.
- Canonical components/API contracts touched: `thread_reactions.js` score
  formatting/parsing/update contract and the QDB `bare-ratio` score format.

## Stage 3

- Goal: lock the visual contract with focused regression coverage and release
  evidence.
- Dependencies: Stages 1–2 complete.
- Expected changes: update affected QDB render assertions and browser
  normalization fixtures; add coverage for the structured live update if the
  current fixtures do not exercise it.
- Verification approach: run the focused QDB display and thread-reaction
  tests, then the relevant application test suite; inspect the changed-file
  scope and asset fingerprinting behavior.
- Risks or open questions:
  - Impact: text-only assertions can miss an incorrect target structure.
  - Early warning / validation: assert both rendered ratio text and the
    score-specific target.
  - Mitigation: cover initial listing/permalink rendering and a live refresh.
- Canonical components/API contracts touched: QDB quote display tests,
  browser reaction fixtures, and fingerprinted QDB theme/reaction assets.
