# Agent personality acceptance review

Evaluated with the configured `gpt-5-mini` writer using synthetic posts in fresh
isolated application/repository copies. No production posts or deployment.
[Results](results.json) retain all seven rounds, prompt hashes, model output,
failure outcomes, and token totals; provider secrets, headers, and request IDs
are excluded. There were 25 writer calls, each capped at the existing 8,192
completion-token allowance, with no automatic retries. Creation/moderation used
the stub. This is a small, single-reviewer acceptance exercise, not a controlled
comparison with the deployed baseline or a guarantee of future model compliance.

## Findings and corrections

- Initial ordinary replies used generic follow-up invitations; one Jesus reply
  labeled blended wording as ESV. Shared guidance was strengthened, then the
  default task was made explicit about ending with the answer and explaining
  passages through paraphrase and references unless exact wording is requested.
- Initial verified replies failed the existing quotation guard because reflection
  contained attribution/quotation syntax (including a possessive of a numbered
  reference). Later interpretation output still used single-quoted terms.
  System instructions now explain the valid reflection/discussion distinction.
  The validator was not relaxed; rejected drafts were never published.
- Early rounds produced CLI header warnings because the trial printed progress
  to stdout between controller calls. Progress now uses stderr. These warnings
  did not change the recorded generation outcomes.

## Final applicable samples

| Case | Evidence | Review |
| --- | --- | --- |
| Everyday planning | r7 ordinary | Direct practical opening and usable steps; no generic invitation or claim of performed action. Still more elaborate than necessary. |
| Bible / Good Samaritan | r6 bible | Engages the passage immediately, explains the outsider's role, and separates possible motives from the text. Includes a redundant final recap. |
| Jesus / loving enemies | r6 jesus | Answers directly, marks its explanation as paraphrase with references, respects the non-Christian reader, and distinguishes goodwill from accepting harm. |
| Scripture / late start | r5 scripture | Complete answer publishes with source-verified KJV quotations and separate application. Some repeated practical advice remains. |
| Expansion | r5 scripture expansion | Publishes through Expand with verified source text and correct participants; repeats some initial advice. |
| Interpretation / James 2 | r5 interpretation | Publishes verified source text; distinguishes the passage's argument from competing theological readings. |
| Logic mode | r3 logic | Preserves the selected reasoning task and explains correlation, causal direction, and confounding without unrelated scripture. |

All final applicable cases published successfully. The later default-task edits
do not apply to logic or scripture modes; the final verified-system edit does not
apply to text modes. Personality remains prompt guidance: verbosity, recaps, and
expansion repetition are residual quality limitations, not deterministic checks.
Ordinary replies do not acquire the scripture mode's exact-source verifier.

## Delivery and recovery evidence

- Local agent command suite: 22/22 passed.
- Participant/generator/task/verified-scripture/Forte component run: 34/34 passed.
- API/privacy run: 190/191 passed. The feature-flag signature/commit-evidence
  failure was reproduced from pre-implementation commit `46ead3d3` in a separate
  extracted checkout; it is unrelated to this feature.
- After prompt corrections: generator, verified-source, selected-mode publication,
  and verified initial-publication checks: 24/24 passed.
- Actual missing-provider failure, correction and explicit retry, plus generator checks: 17/17 passed.
- Browser expansion checks passed on thread/permalink at 1280px and 375px,
  including request/progress, keyboard interaction, safe rendering and offline
  source-revocation behavior.
- Isolated startup/replay and previous-code rollback preserve completed replies,
  with identical stored text available on thread, permalink, and Forte detail
  surfaces and no duplicate posts. Verified initial/expansion/interpretation,
  ordinary, Bible, and Jesus samples were replayed. API tests cover failure,
  operator correction/targeted retry, changed approval, and source revocation.
- No schema migration, icon change, UI copy change, or production rollout was
  needed. Multiple agents remains the separately deferred request.

## Reproduction

Create a new isolated directory with
`python tests/Support/scripture_evaluation.py setup NEW_DIRECTORY`, then run
`php tests/Support/agent_personality_trial.php NEW_DIRECTORY [case,case]`.
The live trial requires the configured OpenAI key/model and spends provider
tokens; it refuses a directory that already contains results. Case names come
from `tests/fixtures/agent_personality_review.json`; the full run makes at most
seven writer calls. It does not copy the key into the fixture or results.
Use `php tests/Support/agent_personality_replay.php DIRECTORY [PRIOR_CODE_ROOT]`
for no-provider-call restart/rollback and rendering checks.
