# Readable message export and clearer pull errors

Cycle v3/export_readable_json · One-shot · [Plans](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/export_readable_json`; host ledger [export_readable_json.md](../effort_ledger/export_readable_json.md). Standing P2 enrollment. Extended estimates: disabled.

**Cycle estimate: 0.15**

Status: planned; next permitted action: implement.

## Original request

The local messages pull failed with "https://zenmemes.com sent an unexpected response (HTTP 200). Press Pull to resume." (Ilya, 2026-11-27). Asked to open `/api/private_messages/export` in his signed-in browser, he pasted the start of it and said: "that json needs to have line breaks, it's just one giant line right now, which is not human-browsable."

## Authorization

The follow-up to [private_message_local_pull](../private_message_local_pull/private_message_local_pull_step4_implementation_summary.md) (merged), done as a one-shot on a new branch from `main` with its own PR. Authorized:
- Pretty-print the export's JSON.
- Make the relay's unexpected-response error say how big the response was and show its start and end, so the failing pull can be diagnosed.

Not authorized:
- Changing what the export contains.
- Changing other endpoints' formatting.
- Guessing at a cause of the pull failure that hasn't been confirmed.

## Problem

- The export is valid JSON on one line. The envelopes and the many items inside it are unreadable in a browser.
- The failed pull says only "unexpected response (HTTP 200)". The export works for the "claude" account through the same relay code: replayed against production on 2026-11-27, it signed in, exported 1 message and confirmed. Ilya's export also starts as valid JSON, so the bad response is something later in his body, or a response cut off at the relay's 8 MiB read limit. The error doesn't say which.

## Outcome and work type

Application change.
- `GET /api/private_messages/export` answers pretty-printed JSON: one field per line, two-level indentation. Armored envelopes stay single JSON strings with `\n` escapes, because JSON cannot hold raw line breaks inside a string.
- When a relayed response is not JSON, the error names the call and the response size, and quotes the first and last 80 characters, with control characters shown as spaces. A response that reaches the relay's 8 MiB read limit is reported as "too large for the relay" instead of as unexpected.

## Risks

- **Other JSON consumers break.** Pretty-printing is still valid JSON, and both the relay and the browser parse it the same way. Limit the change to the export so no other endpoint's bytes change.
- **The error quotes private content.** The start of an export is ciphertext and metadata only. Quote at most 80 characters from each end, and only in the local page's error, which goes to the member who signed in.
- **Page size grows.** Indentation adds a few percent to the response. That's fine within the 2 MiB envelope budget.

## Reuse decisions

- `RouteServices::sendJson()`: extend with an optional pretty flag instead of encoding JSON by hand in the export controller.
- `LocalDevelopmentRemote::json()` and `streamTransport()`: extend in place. They are the only parsing path for relayed responses.

## Concepts touched

[Participant mailbox export](../../concepts/participant_mailbox_export.md): note that the export is pretty-printed.

## Verification plan

- `PrivateMessageExportTest` or a route-level test for pretty output. `LocalDevelopmentImportTest` for the error text, including the too-large case. Run them with the smoke set.
- Replay the reported condition: after deploy, Ilya presses Pull. The error, or a clean pull, then says what really happens. Until then, the original failure is recorded as unverified here.

## Study checkpoint

Intake 2026-11-27T18:55Z, scope v1, R1, P2 standing enrollment; parent `v3/private_message_local_pull`. Claude, single exposed rater; inspected the code and replayed production before rating. **0.15 projected total.** Not hours or measured labor.

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*
