# LLM Generated Thread Titles Plan

## Goal

Generate helpful display titles for titleless threads asynchronously, without rewriting user-authored post content.

## Product Rules

- User-authored `subject` always wins.
- Generated titles are metadata, not canonical authored content.
- The deterministic body excerpt remains the fallback when no generated title exists.
- Display precedence should be:
  1. User subject
  2. Approved/generated title
  3. Deterministic body excerpt
  4. Thread id fallback

## Data Model

- Add generated-title storage outside the canonical post record.
- Store at least: `thread_id`, `title`, `provider`, `provider_model`, `status`, `confidence`, `body_hash`, `created_at`, `updated_at`, and failure details.
- Keep generated title records idempotent by `thread_id` plus `body_hash`.

## Worker Shape

1. Add a script or command for cron execution, for example `scripts/generate_thread_titles.php`.
2. Select titleless threads where:
   - `subject` is empty.
   - Body is non-empty.
   - No completed generated title exists for the current body hash.
   - No generation is currently in progress.
3. Ask the configured LLM for a concise, neutral title.
4. Validate output:
   - Non-empty.
   - Short enough for feed display.
   - No line breaks.
   - Passes the same authored text safety constraints used for subjects.
5. Persist completed titles as metadata.
6. Record retryable and terminal failures separately.

## Rendering Integration

- Extend the thread title helper to accept generated title metadata.
- Update board, tag, profile, thread, API list, and RSS title paths to use the same precedence.
- Keep canonical `Subject:` output unchanged in raw thread APIs unless explicitly adding a separate `Generated-Title:` field.

## Tests

- Unit-test title precedence.
- Smoke-test titleless thread display with and without generated metadata.
- Test stale generation when body hash changes.
- Test provider failure does not break board/thread rendering.
- Test user-authored subject overrides existing generated metadata.

## Open Questions

- Should generated titles be visible to everyone immediately, or only after approval?
- Should approved users be able to reject or replace generated titles?
- Should generated titles be materialized in the read model or loaded from a separate metadata store at render time?
