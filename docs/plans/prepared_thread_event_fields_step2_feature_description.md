> **Feature plan:** [Step 2](./prepared_thread_event_fields_step2_feature_description.md) · [Step 3](./prepared_thread_event_fields_step3_development_plan.md) · [Step 4](./prepared_thread_event_fields_step4_implementation_summary.md)

## Problem

The signed "Create thread" submit path (used by any identity-authenticated member, as opposed to "Create thread anonymously") silently drops `event_date`/`event_time`/`event_location`/`event_link` in two places — the client never collects them, and the server's `prepareThread()` never reads or forwards them — so a signed-in member who fills in event fields loses them without any error.

## User Stories

- As a signed-in member creating an event thread via the normal "Create thread" button, I want my event date/time/location/link to actually be saved, so I don't have to post anonymously or lose the details I entered.
- As a site operator with event support disabled, I want the signed-thread path to reject event fields the same way the anonymous/API path already does, so the feature-flag gate can't be silently bypassed by one write path and not the other.
- As a developer, I want `prepareThread()` to validate event fields the same way `createThread()` does, so malformed input fails closed instead of being silently dropped or stored unchecked.

## Core Requirements

- `collectThreadSubmitFields()` (`public/assets/browser_signing.js`) collects `event_date`, `event_time`, `event_location`, `event_link` alongside the fields it already collects.
- `LocalWriteService::prepareThread()` reads, validates (the same normalizers `createThread()` already uses), and forwards all four event fields into `buildThreadPostRecord()`.
- `prepareThread()` calls `assertEventSupportAllowsInput()` the same way `createThread()` does, so the feature-flag gate applies uniformly across both write paths.
- No change to the anonymous-submit path or to `createThread()`/`/api/create_thread` — both are already correct (verified in the `event_time` cycle).
- Malformed event input submitted via the signed path is rejected with a clear error before any prepared-post token is issued, not silently dropped or stored invalid.

## Delivery Scope

- **Work type:** Application change (one client-side JS function, one server-side PHP method).

## Completion Boundary

- **Normal entry:** A signed-in member on a profile with event support enabled fills in event fields and clicks "Create thread" (not "Create thread anonymously").
- **End-to-end outcome:** The resulting thread's canonical record (and read model) carries the submitted event fields, identical in shape to what the anonymous/API path already produces.
- **Needed recovery:** Malformed event input is rejected with the existing compose-error pattern before signing/prepare succeeds — same fail-closed behavior `createThread()` already has.
- **Release condition:** Full test suite green; a live signed-thread creation (prepare → finalize) with event fields round-trips correctly through the read model and event block.

## Risks

- **High risk:** a client/server field-name mismatch (e.g. a typo in either file) would reintroduce a silent drop in one direction. *Mitigate:* reuse the exact field names already shipped in `createThread()`/`thread_compose_form.php` — `event_date`, `event_time`, `event_location`, `event_link` — not invented fresh.
- `prepareThread()` currently has no `assertEventSupportAllowsInput()` call at all; adding it tightens the gate, not just completes a feature. *Validate:* confirm this matches `createThread()`'s existing behavior exactly (same method, same four field names), so it's closing a pre-existing inconsistency between the two write paths, not introducing new behavior. Early check: Step 3's first stage.
- The prepare/finalize flow is security-sensitive (the client signs whatever canonical record the server's `prepareThread()` builds, and `createPreparedPost()` verifies that signature against the record as submitted). *Validate:* confirm `createPreparedPost()` itself needs no change — it already takes the canonical record text verbatim and verifies it; only what `prepareThread()` puts into that text needs fixing.

## Shared Component Inventory

- `LocalWriteService::buildThreadPostRecord()` / `normalizeEventDate()` / `normalizeEventTime()` / `normalizeAuthoredLine()` / `assertEventSupportAllowsInput()` — all already exist (shipped across the Cycle 4 events feature and this week's `event_time` cycle); reused verbatim, no new validator.
- `browser_signing.js`'s `collectThreadSubmitFields()` — the single existing field-collection function for the signed thread-submit path; extended in place, no new collection mechanism.
- `event_block.php` / read model / `ThreadRepository` — untouched. Once the canonical record carries the right headers, the already-verified render/read paths from the `event_time` cycle handle the rest with no further changes.

## Simple User Flow

1. A signed-in member on a profile with event support enabled opens the full compose form, fills in event date/time/location/link, and clicks "Create thread."
2. The browser collects all fields (including the event ones), requests a prepared/signed record from the server, signs it client-side, and finalizes it.
3. The resulting thread's canonical record carries the event headers, exactly as the anonymous path already does.
4. The thread renders its event block correctly on the board and thread page.

## Success Criteria

- A live signed-thread-creation call (prepare → finalize) with all four event fields set produces a canonical record containing all four headers.
- The same call with a malformed `event_time` is rejected before a prepare token is issued.
- The anonymous-submit path and `createThread()`'s own behavior are unchanged (confirmed via the existing test suite).

Waiting for "Approved Step 2" before drafting Step 3.
