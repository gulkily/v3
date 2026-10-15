> **Feature plan:** [Step 1](./account_key_page_simplification_step1_solution_assessment.md) · [Step 2](./account_key_page_simplification_step2_feature_description.md) · [Step 3](./account_key_page_simplification_step3_development_plan.md) · [Step 4](./account_key_page_simplification_step4_implementation_summary.md)

Debug mode: enabled

## Original Query
Let's start a new cycle. There is way too much going on this page. We want to strip away anything non-essential, and hide under expandos everything that a user does not need most of the time. Keep in mind that this board generally treats keys as disposable and backup/restore is an edge case.

## Understood Intent
Simplify `/account/key/` after the previous redesign: the common visit is "am I set up, as whom, and do I want a different name or a fresh start?" Everything else, including backup and restore, becomes secondary. Key handling, signing, storage, and server behavior stay unchanged, and nothing technical is removed outright unless it is redundant.

## Problem Statement
The page now shows a lead paragraph, a status card, a full backup/restore section, and a four-group technical drawer, which is far more than the one thing most visitors come to do.

## Option A: Demote backup/restore and trim copy
Keep the current structure but return backup/restore to a collapsed expando, remove the lead paragraph and explanatory sentences, and leave the technical drawer as is.

- **Pros:** Smallest change; mostly deletions; hooks and tests barely move.
- **Cons:** The technical drawer still holds duplicated actions (copy/load/restore buttons beside viewer copy buttons) and a long "where things live" list, so the expanded page stays busy.

## Option B: Status card plus three sibling expandos
Show only the status card (state, username, one primary action, clear identity). Below it, three collapsed expandos at the same level and with equal weight: Back up or restore, Keys (public and private key, link a key), and Storage and privacy (what lives in the browser, guest publication, identity hint). There is no outer "More" wrapper, so technical users reach keys and storage in one click, the same as backup. Duplicate controls are consolidated (viewer copy buttons replace separate copy-key buttons) and copy that restates the obvious is dropped.

- **Pros:** The default page is one card; each secondary area is one click away and equally discoverable; every capability stays reachable; duplication goes away.
- **Cons:** Touches template, CSS, and the script's button hooks; removing duplicate buttons needs care with the script's click delegation and tests; the `#key-backup` anchor must still open its expando; three headers sit below the card, so they must stay quiet.

## Option C: Status card only, with technical tools on a separate page
Reduce `/account/key/` to the status card and a link to a new "Key tools" page for backup, restore, viewers, and the link form.

- **Pros:** Cleanest main page; room to grow the tools page.
- **Cons:** New route, navigation, and script wiring for two pages; backup-reminder deep links must be redirected; larger than one slice and adds a surface to maintain.

## Revised Direction
Technical users are a core audience here, so keys and storage are not edge cases in the way backup and restore are. They should sit at the same level as backup and restore, not nested beneath a single "More" expando. Option B is revised accordingly: three sibling expandos under the status card.

## Recommendation
Choose **revised Option B**, borrowing Option A's copy trimming. It is one releasable slice: a visitor sees one card and one action, and can open backup/restore, keys, or storage in a single click, with the page behaving identically to before. Keep the `#key-backup` anchor opening its expando so existing reminder links still land correctly, and defer Option C unless the tools later need their own space.
