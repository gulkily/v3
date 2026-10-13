> **Feature plan:** [Step 1](./readme_and_essay_homepage_step1_solution_assessment.md) · [Step 2](./readme_and_essay_homepage_step2_feature_description.md) · [Step 3](./readme_and_essay_homepage_step3_development_plan.md) · [Step 4](./readme_and_essay_homepage_step4_implementation_summary.md)

Debug mode: enabled

## Original Query
I want the current index.html to actually be our README, and I want index.html to be mostly realspike.txt. Can you one-shot this, or should we do a cycle? Please write Step 1.

## Understood Intent
`realspike.txt` is read as `railspike.txt`, the maintainer's essay in the repository root. The homepage (`index.html`) should become that essay, and `README.md` should carry what the homepage says now: the claim, install steps, the real worked example, and the limits. A cycle was chosen over a one-shot because `scripts/check-railspike-git-workflow.sh` extracts the update block from `README.md` by marker, and other documents link its anchors.
Debug mode is inherited from the previous cycle as stated in the conversation; it can be disabled on request.

## Problem
The homepage and README are written for each other's audience: the README carries reference detail a first-time visitor does not need, and the homepage lacks the maintainer's own story.

## Option A — Replace the README, move the reference material
Make `README.md` the current homepage content, and move the installation checks, update procedure, debug-mode guide, and diagrams into separate files under `docs/`. Repoint the update-check script and any anchors at the new locations. `index.html` becomes the essay with a short install block and links.

- **Pros:** Short README with the claim and install path first; reference material becomes independently linkable.
- **Cons:** Largest change; moves a procedure the test script depends on; many links and anchors to repair and re-verify.

## Option B — Put the homepage content on top of the README
Keep the existing reference sections in place, and replace the README's opening (tagline, "Why I built this", quick start) with the homepage content, merging duplicates. `index.html` becomes the essay with a short install block and links.

- **Pros:** Script markers and reference anchors keep working; smallest set of repairs; delivers both requested swaps.
- **Cons:** README stays long; the opening needs careful reconciliation to avoid duplicating the quick start and "when to use it" sections.

## Option C — Essay on the homepage only
Replace `index.html` with the essay and leave `README.md` as it is, linking it from the page.

- **Pros:** One file, no risk to scripts or anchors; a one-shot.
- **Cons:** Does not make the README the homepage content, so the requested swap is only half done and the worked example and limits lose their home.

## Original Recommendation (superseded)
**Option B.** It makes both swaps while leaving the update block, its test, and the reference anchors in place; any later extraction of reference material (Option A) can be its own cycle. `index.html` becomes the essay, followed by a short install block and links to the README. It stays one releasable slice: `README.md` and `index.html` together, with the link check and the update-workflow script passing. Step 2 should settle the exact page content ("mostly" the essay), where the worked example and limits land, and which README sections are merged or removed.

## Earlier Revision (superseded)
The user first directed that no typos be fixed and no copy changed; the essay stays verbatim, including "promt". That constraint carries into the revision below.

## Revised Direction
The user replaced the plan (revision, not yet approval): **Option C, with the homepage as a simply formatted `railspike.txt` and the README kept exactly as is.** The no-edits constraint stands: the essay's words, typo, and punctuation are unchanged, and only structure (paragraphs, the list, a title) is added.

Rechecked tradeoffs and risks:
- **Scope shrinks to one file:** only `index.html` changes (plus `railspike.txt` being referenced as the source); the README, the update-check script, and all anchors are untouched, so the earlier script risk disappears.
- **Dropped content:** the worked example, limits section, and install command leave the homepage; they stay in Git history, and the README keeps its own install and "when to use it" sections but has no worked example. The page needs one link to the repository so it is not a dead end; Step 2 should confirm that link and whether a title is added above the essay's own first line.
- **Visibility of the typo:** accepted, as before; a verbatim text comparison is the verification.
- **Process fit:** with a single file, no script dependency, and no copy decisions, this is small enough to apply directly as a one-shot if the maintainer prefers; the cycle is still available.
