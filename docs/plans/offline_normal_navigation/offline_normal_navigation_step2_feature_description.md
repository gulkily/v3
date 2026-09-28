# Offline Normal Navigation Step 2 Feature Description

## Problem

The current prototype reads a bounded public snapshot only at `/offline/`; readers expect recent public board and thread URLs to remain usable when a connection is unavailable.

## User stories

- As a reader, I want the normal board URL to show my saved recent public threads offline so that I can continue browsing naturally.
- As a reader, I want a saved thread's normal URL to open offline so that links and history remain useful.
- As a reader, I want clear unavailable and reconnecting states so that I do not mistake limited saved content for the live site.
- As an operator, I want the existing public-data bounds to remain intact so that offline access does not expose private or operational data.

## Core requirements

- Support offline reading at the normal public board URL and normal URLs for threads contained in the latest snapshot.
- Preserve ordinary online behavior when connected; offline content must visibly identify its saved timestamp and limited scope.
- Keep all writing, reactions, tagging, profiles, search, feeds, tools, and unsupported content online-only with a clear reconnect path.
- Use the established bounded public snapshot; do not cache the full static release or personalized data.
- Keep approved-members-only deployments outside the offline cache and navigation path.

## Shared component inventory

- **Public snapshot and hidden offline reader:** reuse as the sole offline content source; extend its presentation capability rather than creating another cache.
- **Board screen:** extend its established recent-thread presentation for snapshot-backed offline rendering so list semantics remain familiar.
- **Thread screen:** extend its established root/reply presentation for snapshot-backed offline rendering; retain existing online action boundaries.
- **Offline cache lifecycle:** extend the existing prototype cache so supported normal navigations share one freshness, privacy, and recovery model.

## User flow

1. Reader visits the public site online; the bounded recent-content snapshot is saved.
2. Reader loses connectivity and opens the normal board URL or a saved thread URL.
3. The supported page renders saved public content and its snapshot timestamp.
4. A missing thread or unsupported destination explains that reconnecting is required.
5. Once online, normal pages resume and the saved snapshot refreshes.

## Success criteria

- With networking disabled after an online visit, the normal board URL shows saved recent threads and a saved thread URL renders its root and replies.
- A thread outside the snapshot and every unsupported route show an explicit reconnect/unavailable state without exposing cached private data.
- Online board and thread behavior, including writes and interactions, remains unchanged.
- Approved-members-only mode neither registers nor serves the offline experience.
