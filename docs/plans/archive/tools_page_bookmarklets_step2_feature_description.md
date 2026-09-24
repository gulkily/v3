# Tools Page Bookmarklets Step 2 Feature Description

## Problem

The site has no dedicated place for user-facing utilities, so bookmarklets and similar helper tools have no clear home. We need a Tools page that introduces bookmarklets in a way that is easy to discover, understandable to non-technical users, and extensible for later tool categories.

## User Stories

- As a forum user, I want one place to find supported tools so that useful utilities are discoverable.
- As a user clipping or reposting content from other pages, I want bookmarklets with clear labels and explanations so that I can use them without guessing what each one does.
- As an operator/developer, I want bookmarklets to live under a stable Tools page instead of a one-off route so that later tools can be added without reorganizing navigation again.

## Core Requirements

- Add a dedicated `/tools/` page as a first-class public route.
- Populate the first version with bookmarklets as the initial tools category.
- Each bookmarklet entry must present a usable bookmark link plus a short plain-language explanation of what it does.
- The page must distinguish between bookmarklets that replace the current page and bookmarklets that open a new window when that difference matters to user expectations.
- The page must be reachable through the shared site navigation.

## Shared Component Inventory

- Shared page shell via `TemplateRenderer` and `templates/layout.php`: reuse as-is for the Tools page.
- Shared top navigation via `templates/partials/nav.php` and renderer-owned `navItems`: extend to add a Tools entry rather than creating page-local navigation.
- Existing simple browse-page/card pattern used by pages such as `/users/` and `/instance/`: reuse as the canonical presentation model for the first Tools page.
- Existing account/compose/browser-signing surfaces: do not reuse as canonical bookmarklet UI; they solve browser identity setup rather than public utility discovery.
- Existing API/text routes: no existing canonical API surface for bookmarklet listings, so the first version should remain HTML-only.

## Simple User Flow

1. User opens `/tools/` from the main navigation.
2. User sees bookmarklet entries grouped with clear labels and short descriptions.
3. User drags a bookmarklet link to the bookmarks bar or activates it from saved bookmarks.
4. User uses the bookmarklet on another page to start a forum-related action with the expected clipping/attribution behavior.

## Success Criteria

- `/tools/` exists and is linked from the shared nav.
- The page clearly exposes the initial bookmarklet set without requiring hidden knowledge or source inspection.
- A user can understand the difference between the available bookmarklets from page copy alone.
- The Tools page establishes a stable home for future utilities without requiring a route rename or navigation reshuffle.
