# PHP Non-ASCII Compose User Failure Assessment V1

This document describes how the current compose flow failed the user when an iPhone smart apostrophe (`’`, U+2019) was entered, submission was rejected, the submit button became disabled after navigating back, and a page reload discarded the drafted message.

## Incident Summary

The user attempted to submit a message containing a common iPhone punctuation character. Instead of helping the user recover, the product created a chain of failures:

- the message was rejected for a character the user did not intentionally choose
- the UI did not preserve a usable recovery path after the rejection
- navigation back left the submit control disabled
- reloading the page destroyed the drafted message entirely

This is not one bug. It is a stacked failure across validation design, recovery UX, draft persistence, state management, and quality control.

## What The User Experienced

From the user’s point of view, the product communicated the following:

- "Your text is invalid for a reason you do not understand."
- "Going back will not restore a working form."
- "Refreshing will delete your work."
- "The system is fragile enough that a normal phone keyboard can break it."

That combination is alienating because it converts a small input mismatch into lost work and a credibility failure.

## Failure Categories

### 1. We rejected normal user input instead of accommodating it

The triggering character was not exotic. It was standard smart punctuation produced automatically by iPhone.

Why this failed the user:

- the product treated a common mobile keyboard default as invalid input
- the rejection happened too late, at or near submit time, after the user had already invested effort
- the system exposed an internal storage constraint instead of absorbing it safely in the compose experience

How to address it:

- normalize a narrow set of common smart punctuation in the browser as the user types or before submit
- keep server-side validation as a backstop, but do not make it the first time the user learns about the problem
- present the issue in plain language tied to the specific field, with one obvious recovery action

This gap is broader than the body textarea. The current compose recovery path is centered on body text, while thread subjects still remain exposed to raw server-side ASCII rejection. A smart apostrophe in the subject can therefore reproduce the same class of failure even if the body path is improved.

### 2. We made the user debug our encoding policy

The burden of understanding unsupported characters was pushed onto the user.

Why this failed the user:

- most users do not know the difference between ASCII apostrophes and typographic apostrophes
- "rejected input" without immediate inline correction feels arbitrary
- the product required the user to discover and resolve a technical implementation detail

How to address it:

- silently replace clearly safe punctuation variants such as smart quotes, dashes, ellipses, and non-breaking spaces
- only interrupt the user for characters that cannot be safely normalized
- if interruption is required, provide a single inline action such as `Remove unsupported characters`

### 3. We broke the recovery flow after the first error

After the rejection, returning to the previous page left the submit button disabled.

Why this failed the user:

- the form was no longer in a valid interactive state
- navigation did not restore a usable compose session
- the user encountered a dead end immediately after an error, which compounds frustration and implies unreliability

How to address it:

- ensure browser back/forward cache restores compose controls correctly
- recompute submit enabled/disabled state from the current form contents on page show, not only on first load
- test recovery flows explicitly: submit failure, back navigation, retry, and refresh

### 4. We destroyed the user’s draft

Reloading the page erased the message.

Why this failed the user:

- losing authored text is the highest-severity compose failure in this sequence
- once the system has already rejected input, preserving the draft becomes even more important
- draft loss teaches the user not to trust the editor

How to address it:

- persist drafts locally for thread and reply compose flows
- restore drafts automatically after reload, navigation errors, and recoverable submission failures
- clear persisted drafts only after confirmed successful submission or explicit user discard

### 5. We turned a small validation problem into a full session failure

Any one of these defects would be frustrating. In combination they form a complete failure of the compose contract.

Why this failed the user:

- input rejection blocked submission
- broken state blocked retry
- reload erased the fallback copy of the user’s work
- each attempted recovery path became worse than the previous one

How to address it:

- treat compose as a resilience-critical flow, not a basic form
- define the invariant that the user must always have a path to either submit, correct, copy, or recover their text
- audit the flow for "failure after failure" chains rather than isolated bugs

### 6. We optimized for canonical storage correctness over user success

An ASCII-only write contract may be acceptable internally, but the user experience cannot expose that constraint raw.

Why this failed the user:

- the storage model leaked directly into the interaction model
- the system protected its data constraints while failing to protect the user’s effort
- correctness was treated too narrowly: the backend stayed strict while the end-to-end product became incorrect

How to address it:

- keep strict backend constraints if needed, but add a forgiving compose boundary in the browser
- define product correctness to include successful recovery, preserved drafts, and predictable form behavior
- measure "user successfully posts" as the primary outcome, not just "server rejected invalid bytes"

### 7. We likely under-tested mobile and navigation edge cases

This incident strongly suggests missing coverage in exactly the places users are vulnerable.

Likely gaps:

- iPhone smart punctuation entry
- submit-time rejection with preserved text
- browser back after failed submit
- reload after failed submit
- disabled-button state restoration

How to address it:

- add focused tests for normalization helpers and unsupported-character handling
- add integration coverage for compose state after browser history navigation
- add smoke coverage for draft persistence and restoration
- include mobile-default punctuation behavior in manual QA checklists

### 8. We did not provide defense in depth

The system had too many single points of failure:

- validation only at the backend boundary
- UI state that could become stuck
- no durable draft safety net
- no graceful fallback when the main path failed

How to address it:

- use layered protection: inline normalization, inline validation, resilient submit-state recomputation, local draft persistence, and backend enforcement
- ensure each layer reduces harm if the previous layer fails

### 9. We likely lack the right product and operational signals

If this failure mode reached a user intact, we may not be tracking the right indicators.

Signals we should have:

- rate of compose submission failures by validation reason
- rate of compose abandonment after submit errors
- rate of reload/back navigation following failed submits
- restoration success rate for local drafts

How to address it:

- instrument compose failure reasons and recovery outcomes
- alert on spikes in validation failures tied to common mobile punctuation
- review abandoned compose sessions as a product-health signal

## Required Product Standard

The compose flow should satisfy these minimum guarantees:

- common mobile punctuation does not cause a hard failure
- unsupported characters are identified before submission, next to the field that needs attention
- the submit button always returns to a correct enabled/disabled state after navigation or reload
- the user’s draft survives reloads and recoverable errors
- no single validation issue can silently destroy authored text

## Recommended Response

### Immediate fixes

- normalize common smart punctuation in the browser
- apply the same normalization and unsupported-character handling to the subject field wherever subjects can be authored
- add explicit unsupported-character recovery for truly unsupported input
- fix submit-button state restoration on page show and history navigation
- persist drafts locally and restore them automatically

### Short-term verification

- add regression tests for iPhone smart apostrophes and related punctuation
- add dedicated regression coverage for smart punctuation in thread subjects, not only post bodies
- add navigation/reload recovery tests for compose pages
- manually verify failure-and-retry flows on mobile Safari behavior patterns

### Process corrections

- treat draft loss as a release-blocking severity issue
- expand QA from "can submit valid input" to "can recover safely from invalid input"
- require end-to-end review of compose resilience before shipping validation changes

## Conclusion

The user was not merely blocked from posting. They were taught that the editor rejects ordinary input, breaks when they try to recover, and cannot be trusted to keep their words safe. The fix is therefore not just character normalization. We need a compose flow that is forgiving at input time, resilient after failure, and durable with user-authored text.
