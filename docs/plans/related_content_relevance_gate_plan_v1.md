# Related Content Relevance Gate Plan v1

## Current Finding
- The current "Possibly related" path is candidate search, not relevance judgment.
- `RelatedContentSearchService::findRelatedContent()` builds up to 12 tokens from the target post subject/body, scans every cross-thread post, and returns any row with positive overlap.
- A match can qualify with one shared non-stopword token in the body, because `scoreRow()` accepts any `score > 0`.
- `PostAnalysisService::analyze()` stores the raw candidate list from analysis context as `related_content` regardless of whether the analyzer actually judged any candidate useful.
- The UI renders stored `related_content` directly for approved viewers, so weak lexical candidates become visible as "Possibly related."

## Problem
- Keyword overlap is useful for recall, but it is not enough for user-facing related-thread display.
- The system currently has no explicit answer to these questions:
  - Is this candidate actually about the same issue, claim, request, or prior answer?
  - Did the target post ask for, solicit, or naturally benefit from related prior discussion?
  - Is showing related content appropriate, or would it distract from the current thread?
  - Should the result set be empty even though lexical candidates exist?
- The existing analyzer prompt tells the model not to cite weak matches in `suggested_response`, but the storage/display layer ignores that distinction and persists all candidates.

## Desired Behavior
- Related content should be a high-precision feature.
- The search service should be allowed to return broad internal candidates, but public/displayed `related_content` should contain only model-approved matches.
- If no candidate is clearly related and appropriate, store and render an empty list.
- Analysis should produce an explicit related-content judgment, including how likely it is that related results were asked for, solicited, or appropriate.

## Proposed Contract
- Keep lexical search as candidate generation, renamed or documented as such.
- Add a model-gated output field, tentatively `related_content_assessment`, to the post-analysis result.
- Store only approved related items in `related_content`.
- Store the assessment beside the analysis so operators can inspect why candidates were accepted or suppressed.

Suggested `related_content_assessment` shape:

```json
{
  "related_results_appropriate": true,
  "solicitation_score": 0.82,
  "solicitation_reason": "The post asks whether this topic was already answered elsewhere.",
  "candidate_reviews": [
    {
      "post_id": "abc123",
      "relationship": "direct_answer",
      "relevance_score": 0.91,
      "appropriate_to_show": true,
      "reason": "Same concrete question and prior answer is reusable."
    }
  ]
}
```

Recommended enums:
- `relationship`: `none`, `same_topic`, `same_question`, `direct_answer`, `duplicate_request`, `background_context`, `counterexample`
- `related_results_appropriate`: boolean, true only when at least one approved candidate should be visible or cited
- `solicitation_score`: number from 0 to 1 rating whether the target post asks for, solicits, or naturally benefits from prior related discussion
- `appropriate_to_show`: boolean per candidate

## Acceptance Rules
- A candidate may be displayed only when all are true:
  - `relevance_score >= 0.75`
  - `appropriate_to_show === true`
  - `relationship` is not `none`
  - the candidate is not the target post and not in the same thread
- If `solicitation_score < 0.35`, default to showing nothing unless a candidate is a direct duplicate or direct answer.
- If all candidates fail the model gate, persist `related_content: []`.
- Cap displayed matches at 3 initially, even if more candidates are reviewed.

## Stage 1 - Tighten Candidate Generation
- Status: Implemented in commit 1 on branch `related-content-relevance-gate`.
- Goal: Reduce obvious false positives before model review.
- Expected changes:
  - Rename internal concepts from "matches" to "candidates" where practical.
  - Require at least two distinct shared useful tokens, or one strong phrase/subject overlap.
  - Add a minimum lexical score threshold higher than `> 0`.
  - Prefer root/thread-level candidates over isolated replies unless reply text carries the strongest evidence.
  - Include diagnostic fields internally, such as matched tokens and lexical score, but avoid rendering them publicly.
- Verification:
  - Add tests proving one-token overlap does not return a candidate.
  - Add tests for strong same-question matches, subject matches, and no-match cases.
  - Completed focused verification with `RelatedContentSearchServiceTest`.

## Stage 2 - Add Model Relevance Assessment
- Goal: Let the analyzer decide whether related results are genuinely related and appropriate.
- Expected changes:
  - Extend the Dedalus response schema with `related_content_assessment`.
  - Update `prompts/dedalus_post_analysis_system.txt` to require candidate-by-candidate relevance review when `related_content` candidates are present.
  - Instruct the model to rate whether related results were asked for, solicited, or appropriate for the target post.
  - Require the model to mark weak or merely lexical overlaps as `appropriate_to_show: false`.
- Verification:
  - Analyzer schema tests cover the new field.
  - Prompt tests assert guidance for solicitation score, candidate review, and suppressing weak matches.

## Stage 3 - Persist Only Approved Related Content
- Goal: Stop storing raw lexical candidates as displayable related content.
- Expected changes:
  - Change `PostAnalysisService::analyze()` so it filters `context['related_content']` through the analyzer's `related_content_assessment`.
  - Save approved candidates to `related_content`.
  - Save the full assessment to a new store field, for example `related_content_assessment_json`.
  - Preserve backward compatibility by hydrating missing assessment fields as an empty/default assessment.
- Verification:
  - Store tests prove approved candidates persist and rejected candidates do not.
  - Write API smoke tests prove unrelated lexical candidates do not appear in approved thread/post pages.

## Stage 4 - UI And API Inspectability
- Goal: Make the decision understandable without overstating uncertainty.
- Expected changes:
  - Continue hiding related content from anonymous viewers.
  - For approved viewers, render only approved `related_content`.
  - Expose `related_content_assessment` in analysis API details for operators/moderators.
  - Consider changing the label from "Possibly related" to "Related prior discussion" only after precision is high; otherwise keep cautious wording.
- Verification:
  - UI smoke test confirms rejected candidates are absent.
  - API detail test confirms assessment metadata is available to approved viewers only.

## Stage 5 - Regression Fixtures
- Goal: Lock in precision-first behavior.
- Test cases:
  - One shared generic domain token returns no displayed related content.
  - A direct duplicate question displays the prior thread.
  - A target post that explicitly asks "has this been discussed before?" can display same-topic background.
  - A target post that does not ask for prior context suppresses merely adjacent same-topic results.
  - A candidate with high lexical overlap but different intent is rejected by model assessment.

## Migration Notes
- This changes analysis output shape and should bump the analysis schema version so stale cached rows do not mask the new gate.
- Existing `related_content_json` rows can remain valid; they should be treated as legacy candidate-derived rows until regenerated.
- The new assessment field should be additive in SQLite and default to an empty object.

## Open Questions
- Should the model receive only candidate excerpts, or full candidate bodies within a separate budget?
- Should solicitation score affect agent reply generation, UI display, or both?
- Should related-content assessment be part of `respondability`, or remain a separate top-level analysis object to avoid conflating "should reply" with "should show prior context"?
- Should root posts and replies have different display thresholds?
