> **Feature plan:** [Step 1](./qdb_numeric_url_retention_step1_solution_assessment.md) · [Step 2](./qdb_numeric_url_retention_step2_feature_description.md) · [Step 3](./qdb_numeric_url_retention_step3_development_plan.md) · [Step 4](./qdb_numeric_url_retention_step4_implementation_summary.md)

Debug mode: disabled

## Problem

QDB quote cards link to short numeric addresses, but runtime requests redirect to long thread URLs. Users lose the readable address they expected to bookmark or share.

## User stories

- As a reader, I want clicking quote #123 to display it at `/123` so that its address stays short and recognizable.
- As a reader, I want refreshing or opening a shared `/123` link to show the same quote so that the address is dependable.

## Core requirements

- A valid numbered quote opens directly at its numeric URL with a successful response and no redirect to a long thread URL.
- Refreshing, bookmarking, and reopening that URL preserve the numeric address and resolve the same quote.
- The numeric page retains the existing quote detail content, score, voting, flagging, and viewer state.
- Existing long links and unnumbered threads remain usable; other site profiles retain their current routing and missing quotes retain the existing not-found behavior.
- Numeric URLs work consistently through runtime delivery and supported static hosting, including fallback when a prebuilt quote page is absent.

## Delivery scope and completion boundary

**Work type:** application change, implementing approved Option A. Reuse the existing quote experience and numbering; exclude page redesign, numbering changes, and redirects from old long links to numeric URLs.

The slice starts with a quote link in a normal listing and ends with a usable quote page at the short address. Missing quotes show the existing not-found page; absent static detail pages fall back to runtime delivery. Release requires verified click-through, refresh, direct reopening, quote interactions, and compatibility across supported delivery paths.

## Risks

- **Delivery mismatch:** Static hosting can retain a short URL while runtime redirects. Earliest validation: compare both paths with and without prebuilt details. Mitigation: make address retention a shared acceptance requirement.
- **Lost page behavior:** A separate numeric page could omit viewer state or controls. Earliest validation: compare the existing detail experience and its interaction dependencies. Mitigation: reuse the canonical detail presentation and interaction APIs.
- **Route compatibility:** Numeric handling could affect other routes or sites. Earliest validation: review route precedence and existing missing-quote checks. Mitigation: restrict the change to QDB numbered quotes and retain compatibility coverage.

## Shared component inventory

- Latest, Top, Leetness, Random, and Search reuse the existing quote cards and numeric links.
- Thread and post detail pages reuse existing quote presentation, viewer state, and vote/flag APIs; extend numeric entry to the established detail experience.
- Tag/board listings and RSS continue using their existing presentations and links; long-link compatibility remains required.
- Static quote detail artifacts reuse the existing detail presentation and runtime fallback. No new UI component or interaction API is needed.

## Simple user flow

1. Open a QDB quote listing and select #123.
2. Read and interact with that quote while the address remains `/123`.
3. Refresh or share the address and reopen the same quote at `/123`.

## Success criteria

- A known quote's numeric URL returns HTTP 200 without a long-route redirect on every supported delivery path.
- Listing click-through, refresh, and direct reopening retain the numeric address and correct quote.
- Existing quote interactions and viewer state work; missing numbers return HTTP 404; long links and non-QDB routing pass compatibility checks.
