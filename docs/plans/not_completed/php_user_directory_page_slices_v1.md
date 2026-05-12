# PHP User Directory Page Slices V1

This document outlines the implementation slices for adding a user directory page to the PHP forum rewrite.

## Goal

Add a `/users/` page that lets people browse forum profiles in one place.

The first retained version should:

- list visible profiles from the read model
- link to each profile page
- show enough summary data to make the listing useful
- avoid leaking hidden bootstrap-only internals into the main browsing surface

## Current Context

The repo already has:

- profile pages at `/profiles/<slug>`
- username routes at `/user/<username_token>`
- a `profiles` read-model table with identity and summary fields
- templated rendering via `TemplateRenderer`
- top-level nav rendering in `templates/partials/nav.php`

So this is mostly a read/render feature, not a new write-path feature.

## Product Direction

The directory should feel like a normal browse page, not an admin/debug screen.

That means the first version should prefer:

- visible username
- profile link
- lightweight profile stats

And should avoid foregrounding:

- raw identity IDs
- signer fingerprints
- bootstrap anchors
- hidden bootstrap-only accounts with no visible participation

## Slice 1: Read Contract And Inclusion Rules

Focus:

- define exactly which profiles belong in the directory

Checklist:

- decide the inclusion rule for profiles with zero visible posts and zero visible threads
- default to hiding bootstrap-only accounts from the directory
- define the sort order for the first version
- decide the summary fields shown in the directory rows/cards
- confirm whether `/users/` should be HTML only in V1 or also gain a text API later

Recommended first-version rule:

- include profiles with at least one visible thread or visible post
- sort by visible thread count descending, then visible post count descending, then username

Expected outcome:

- the directory has a clear product rule and does not expose technical/bootstrap noise

## Slice 2: Application And Query Support

Focus:

- add the route and fetch logic in the application layer

Checklist:

- add a `GET /users/` route in `src/ForumRewrite/Application.php`
- add a `fetchUserDirectoryProfiles()` query against the `profiles` table
- only return fields needed by the directory template
- filter out bootstrap-only profiles according to the chosen rule
- implement stable ordering in SQL
- add a renderer method such as `renderUserDirectory()`

Expected outcome:

- the app can serve a user-directory page from the existing read model

## Slice 3: Template And Navigation

Focus:

- render the directory cleanly and make it reachable

Checklist:

- add `templates/pages/users.php`
- render each user with:
  - visible username
  - profile link
  - `/user/<username_token>` link if useful
  - visible thread/post counts
- add an empty-state message for when no visible profiles qualify yet
- add a nav item for `/users/`
- keep the HTML source readable under the existing template formatting rules

Expected outcome:

- users can discover and browse the directory from the site nav

## Slice 4: Tests And Surface Consistency

Focus:

- verify the new route behaves correctly and matches existing profile semantics

Checklist:

- add smoke coverage for `GET /users/`
- verify the page links to known visible profiles
- verify bootstrap-only profiles are excluded by default
- verify the nav includes the directory route
- verify the empty state renders correctly when no visible profiles exist

Expected outcome:

- the new page is covered and stable

## Slice 5: Optional Follow-On Improvements

These are not required for the first retained slice.

- add a text API such as `GET /api/list_profiles`
- add pagination if profile count grows
- add sorting options
- add a small search box
- add an operator/debug mode that can reveal bootstrap-only accounts when explicitly requested

## Recommended Order

1. lock the inclusion/sort rules
2. add fetch and route support
3. add the page template and nav link
4. add tests
5. decide later whether API/search/pagination are worth another slice

## Summary

This feature should start as a small browse surface built on top of the existing `profiles` read model.

The key product choice for V1 is:

- show normal visible forum participants
- hide bootstrap-only accounts from the default directory
