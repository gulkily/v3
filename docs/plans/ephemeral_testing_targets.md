# Ephemeral Testing: Candidate Targets

Source: todo.txt item "identify targets for ephemeral testing", which references
https://zenmemes.com/threads/thread-20261006122804-184a64b6 (a Daniel Lemire post, October 2026).

## The idea

Ask an AI agent to build something disposable on top of your code, then judge how
hard that was. The throwaway layer is the test; the agent's struggles are evidence
about your code, not about the agent.

- Clean API, stable invariants, useful errors: the agent produces working software quickly.
- Hidden state, surprising defaults, incomplete docs: many patches and failures.

The post names no specific targets, metrics or tools, so choosing targets is up to us.

## What makes a good target

A seam where an outsider must build against our contract using only the docs.

## Strong candidates

1. **Read-only interface from a database dump** (original idea).
   Tests the SQLite read model, the query catalog in `queries/sqlite`, and whether
   `docs/specs/read_model_schema_v1.md` and `scripts/rebuild_read_model.php` are
   documented well enough to use without reading source. (That schema doc says "if this
   drifts from the code, the code wins", so drift is itself worth checking.)
2. **Independent canonical-record parser and verifier.**
   Give the agent only the record specs in `docs/specs/` (`canonical_post_record_v1.md`,
   `identity_bootstrap_record_v1.md`, `public_key_storage_v1.md`, and the other `*_record_v1.md`
   files) plus `docs/architecture/public_architecture_and_trust.md`.
   Have it write a parser plus signature checker in another language (Python or JS)
   and compare against `CanonicalRecordParsersTest` and `scripts/audit_post_signatures.php`.
   Each divergence is a spec gap or a bug. Probably the highest-signal test.
   Note: the signature rules are scattered (detached signatures "adjacent to signed
   records" is stated in several specs, but no single spec defines exactly what is
   signed or how verification works), so expect this task to surface spec gaps.
3. **Third-party client or bot.**
   Generate a key, sign a post and submit it using only public docs. Tests the signing
   flow, browser-signing normalization and rejection error messages. Also informs the
   private-messages key-backup question.
4. **Alternate static renderer.**
   Build a different static site (feed, single-page archive viewer) from
   `state/static_html` or the repository archive. Tests `scripts/build_static_artifacts.php`,
   the offline snapshot format, and coupling to templates.

## Smaller experiments

5. **Import/export round-trip.** Write an importer for a foreign format (mailing list,
   forum export) using `scripts/qdb_archive_import_*` as the documented precedent.
6. **Agent-reply provider swap.** Add a new provider behind the structured-chat interface
   (see `scripts/test_agent_reply_provider.php`). Tests whether the provider contract is a
   real interface or implicit coupling to Anthropic.
7. **Operator CLI.** Build a status or health report using only `scripts/*.php` and their
   `--help` output. Tests script discoverability.
8. **Blind deploy.** A fresh agent stands up a new instance from `README.md` and
   `CONTRIBUTING.md` alone in a clean directory. Cheapest test of onboarding docs.

9. **SFENC encoder/decoder.** `sfenc.md` is the Starfield Encoding Specification: a
   self-contained spec for encoding binary data into a starfield-looking PNG. It is
   unrelated to the forum's record format (an earlier draft of this document wrongly
   cited it as the record spec). Having an agent write an encoder and decoder from the
   spec alone is a clean spec-quality test, though low priority for the forum itself.
   Note the file begins with a stray LLM chat line that should be removed.

## Suggested protocol

- Give each agent only the public docs and a one-paragraph goal; no source tour.
- Log every question it has to ask and every workaround it makes.
- Classify each log entry as a doc fix, an error-message fix, or an API change.

## Recommended start

Run #1 and #2 together. #1 is cheap and exercises the data layer; #2 exercises the
contract everything else depends on.

## Open follow-up

Draft a short protocol (prompt template plus findings log format) for #1 and #2.
