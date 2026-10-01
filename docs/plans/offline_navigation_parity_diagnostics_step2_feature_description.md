# Offline Navigation Parity and Diagnostics Step 2 Feature Description

## Problem

Readers cannot tell whether a missing offline-navigation control is an
unsupported capability or a stale cached reader, and the offline Board subnav
does not closely match the normal Board navigation.

## User stories

- As a reader, I want the offline Board subnav to retain its normal read navigation so that I can reach saved content naturally.
- As a reader, I want New Post to remain visibly online-only so that a missing write action is not mistaken for a stale page.
- As a reader, I want the saved archive generation time and reader revision displayed so that I can judge the freshness and capability of offline content.
- As an operator, I want the health page to distinguish cached-reader freshness from snapshot freshness so that I can give readers a clear recovery action.

## Core requirements

- Mirror the normal Board read-navigation controls offline, including Tags and Board filters/sorts; retain New Post as an explicit reconnect-only affordance.
- Identify the saved archive's generation time and the cached reader revision on offline screens.
- Let the Offline Reading health page report whether the cached reader matches the currently available reader while online, and state when comparison is unavailable offline.
- Provide a clear online refresh/recheck path that updates reader assets and archive data without caching normal rendered documents.
- Preserve public-snapshot bounds, normal-route fallback behavior, and all online-only write/personalized-route exclusions.

## Shared component inventory

- **Board and Tags subnav:** reuse the established online control labels and destinations; extend offline presentation with supported links and an explicit reconnect-only New Post treatment.
- **Offline snapshot reader:** extend its existing mode/status presentation with archive metadata, reader revision, and supported-capability context.
- **Offline Reading health page:** extend the canonical cache/asset/publication checks with cached-versus-live reader freshness and refresh guidance.
- **Service-worker cache lifecycle:** reuse the existing refresh message and versioned cache; extend only the status contract required for trustworthy diagnostics.

## User flow

1. Reader opens Board or Tags online; the reader shell and public snapshot refresh.
2. Offline, the Board subnav identifies supported read navigation, New Post's reconnect boundary, archive generation time, and reader revision.
3. Reader opens Offline Reading health online to compare saved and current reader status, then refreshes or rechecks if needed.
4. Reader retries offline navigation with an explicit explanation of any remaining limitation.

## Success criteria

- Offline Board exposes Tags plus every normal Board read control, and New Post clearly requires reconnection.
- Offline reader displays its archive generation time and cached-reader revision.
- While online, health reports whether the saved reader is current; while offline, it explicitly says live comparison cannot be made.
- A documented refresh/recheck flow resolves an outdated cached reader without storing normal Board or Tag page copies.
