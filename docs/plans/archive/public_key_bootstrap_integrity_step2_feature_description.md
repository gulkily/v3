# Public-Key Bootstrap Integrity Step 2 Feature Description

## Problem

New accounts receive a hidden bootstrap post that is linked to their public key but is not signed, so visitors cannot distinguish identity association from cryptographic authorship. Repeating setup with the same key also produces a technical duplicate-identity error instead of a clear recovery path.

## User Stories

- As a new account owner, I want my automatically created bootstrap statement signed by my key so that it proves the statement’s authorship.
- As a visitor, I want bootstrap-post status to distinguish key association, approval, and signature verification so that I do not infer trust from the wrong signal.
- As a visitor, I want to reach and understand a hidden bootstrap post from the profile so that the account does not appear incomplete.
- As an account owner, I want repeating setup with an already-linked key to take me to the existing identity so that I can recover without a duplicate error.
- As an operator, I want failed bootstrap signing to avoid presenting an apparently verified or partially completed identity so that repository state remains trustworthy.

## Core Requirements

- Newly auto-created bootstrap posts must have a detached signature verifiable with the public key linked to the resulting identity.
- The UI must report identity association, public-key availability, approval state, and bootstrap-post signature verification as separate facts.
- Hidden/internal bootstrap posts must remain excluded from ordinary feeds and counts while remaining reachable from the profile with clear explanatory context.
- Repeating identity setup with an existing fingerprint must be recoverable through the existing profile, without creating a second identity.
- Existing manually supplied or legacy unsigned bootstrap anchors must not be mislabeled as signed; their status must remain explicit.

## Shared Component Inventory

- `templates/pages/account_key.php`: existing browser setup and advanced “Link identity” surface; extend its duplicate/recovery feedback while preserving the primary setup flow.
- `LocalWriteService::linkIdentity()` and the account-link route: existing identity creation contract; extend the account result/error behavior for signed auto-bootstrap creation and existing fingerprints.
- `templates/pages/profile.php`: existing profile identity-details surface for approved and unapproved users; reuse it for separate key, approval, bootstrap, and signature facts.
- `templates/pages/post.php` plus `templates/partials/post_card.php` and `templates/partials/thread_root_card.php`: existing post source/signature and bootstrap key presentation; extend the canonical status presentation rather than adding a parallel post view.
- `OpenPgpSignatureVerifier` and existing signature status/audit paths: canonical signature verification behavior; reuse the same verification rules and status vocabulary.

## Simple User Flow

1. User completes browser key setup or submits a new public key.
2. Account creation creates the identity and its automatic bootstrap statement with a verifiable signature.
3. User is taken to the existing profile; the profile links to the hidden bootstrap post.
4. Visitor opens the bootstrap post and sees the public key plus distinct association, approval, and signature status.
5. If the user repeats setup with the same key, the account page identifies the existing profile and offers a direct recovery path.

## Success Criteria

- Every newly auto-created bootstrap post has an adjacent signature that verifies against its linked public key.
- A new account’s profile and bootstrap-post views show consistent key, approval, and signature states without calling an unsigned post verified.
- Hidden bootstrap posts remain absent from ordinary feeds/counts but are reachable through the profile link.
- Repeating setup with the same fingerprint produces no duplicate identity and provides a usable existing-profile destination.
- Legacy/manual unsigned anchors continue to render as explicitly unsigned, with no regression to ordinary signed-post verification.
