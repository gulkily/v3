# Multi-Site Config — Step 2: Feature Description

## Problem

One codebase must serve zenmemes.com and chouse.club as separately branded
deployments (site name, default theme, composer copy) without hardcoding
either site's identity into the code zenmemes already runs, and developers
need to preview either site's branding locally without hand-editing shell
config each time.

## User Stories

- As a site operator, I want each vhost to declare which site profile it
  serves so that zenmemes.com and chouse.club render correct branding
  without a code change per site.
- As a developer, I want to switch the active site profile while running
  the app locally so that I can preview chouse's branding in the same
  checkout I use for zenmemes, without a second clone or a permanent shell
  export.
- As the site owner, I want adding a future third site to require only a
  new profile entry, not new plumbing scattered through the app.

## Core Requirements

- A single registry holds one entry per site (name, default theme,
  composer copy) — one source of truth, mirroring the existing
  `ThemeRegistry` pattern.
- The active profile resolves from an environment variable
  (`FORUM_SITE_ID`), consistent with the existing `FORUM_*` precedent; an
  absent value defaults to `zenmemes`, so the current deploy is unaffected.
- Every hardcoded `SiteConfig::SITE_NAME` reference reads from the active
  profile instead.
- Local/dev runs support switching the active profile per-invocation
  (e.g. a one-line prefix or CLI convenience on `./v3 start`) without
  editing persistent shell config, so both profiles can be previewed from
  one checkout.
- A vhost with a site ID that doesn't match its content/database paths is
  a misconfiguration, not a supported mode — no code attempts to reconcile
  the two automatically.

## Shared Component Inventory

- `SiteConfig::SITE_NAME` is already the single choke point all callers go
  through (`Application.php`, `TemplateRenderer.php`,
  `Host/FrontController.php`, `templates/pages/about.php`) — this feature
  extends that existing accessor rather than introducing a parallel
  identity lookup.
- `ThemeRegistry` stays the canonical list of available themes; the new
  registry only adds a per-site *default* selection, it does not
  duplicate or replace theme definitions.
- No new UI surface is introduced — existing templates keep calling the
  same accessors, which now return per-site values.

## Simple User Flow

1. Operator sets `FORUM_SITE_ID` (plus the existing content/database
   `FORUM_*` vars) in a vhost's config.
2. A request arrives; the app resolves the active profile once per
   request from that env var.
3. Every place that currently renders `SiteConfig::SITE_NAME` or picks a
   default theme uses the resolved profile's values instead.
4. Locally, a developer selects a profile per run/session and sees the
   corresponding branding without touching the codebase.

## Success Criteria

- Zenmemes' deployed behavior (name, theme, copy) is unchanged with
  `FORUM_SITE_ID` unset.
- Setting `FORUM_SITE_ID=chouse` changes site name, default theme, and
  composer copy everywhere they currently render, with no remaining
  hardcoded `zenmemes` string in the affected call sites.
- A developer can preview both profiles locally in the same checkout
  within a single session, without editing a persistent config file.
