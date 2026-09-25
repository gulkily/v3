# Fast Post Scoring

Fast post scoring provides one focused 0–1 probability without running full post analysis. It is disabled by default and is intended for approved operators or internal workflows.

## Configuration

Set `FAST_SCORING_ENABLED` to `true`. The scorer uses the normal `LLM_*` provider settings unless any `FAST_SCORING_LLM_*` override is present; at minimum, set `FAST_SCORING_LLM_MODEL` to a lower-cost model suitable for a short classification request.

`FAST_SCORING_PROMPT_PATH` selects the rubric prompt. Its text must define exactly what probability 0 and 1 mean. The model receives the target post text; replies additionally receive bounded parent and root context. It returns only a numeric `probability` value.

## API

`POST /api/score_post?post_id=<id>` requires an approved viewer. Its response includes `post_id`, `status`, nullable `probability`, `source` (`llm`, `heuristic`, or `none`), and `signals`.

- `scored` results have a probability in the inclusive 0–1 range.
- `excluded` is a deterministic, heuristic-only outcome with no probability.
- `disabled`, `config_missing`, `provider_error`, and `invalid_response` are explicit non-score outcomes.

Model exchanges use the existing private LLM-exchanges audit surface. The endpoint does not publish a score on post cards or make a reader-facing threshold decision.
