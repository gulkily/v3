# Feature Request FDP Intake Step 1 Solution Assessment

## Problem Statement

Approved users need a structured way to turn forum feature requests into FDP work, including thread documentation, user-story translation, opt-in controls, cron discovery, and feature branches. Source: `thread-20260826033010-252730f9`, submitted 2026-08-26T03:30:10Z.

## Option A: Add a manual "Start FDP" action on eligible threads

Pros:
- Keeps humans in control.
- Directly connects request threads to planning artifacts.
- Lower risk than automatic conversion.

Cons:
- Requires users to notice and trigger the action.
- Does not discover missed requests automatically.

## Option B: Add automated feature-request discovery plus draft FDP suggestions

Pros:
- Helps surface new requests.
- Can prepare user stories for review.
- Reduces manual triage work.

Cons:
- Classification mistakes can create noisy drafts.
- Needs moderation and approval boundaries.

## Option C: Add a full integrated FDP workflow UI

Pros:
- Best long-term workflow for Step 1 through Step 4.
- Can show status, approvals, and branch links in one place.

Cons:
- Larger product surface.
- More state and authorization decisions.

## Recommendation

Recommend Option A first, followed by Option B.

Brief justification:
- Manual opt-in should establish the safe handoff contract before a cron job starts discovering and proposing FDP work.
