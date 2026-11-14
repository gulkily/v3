Title: Reading margins
Status: implemented behind FORUM_READING_MARGINS_ENABLED (off by default)
Workflow: Direct
Feedback: none matching in docs/feedback/items/

Request:
Add the radio-button margin ruler from https://www.gregegan.net/ESSAYS/GOOGLE/Google.html
to v3 under a feature flag. On that page a row of radio buttons sits above the text; each
button is at the horizontal position it stands for, and the two checked buttons are the
column's left and right edges.

Behavior:
- Thread pages only (thread.php), above <main>. Left stops at 4/14/24/34vw, right stops at
  66/76/86/96vw; each radio is drawn at its own position, so the row needs no labels.
- Choosing one edge while the other is unset also checks the stop nearest the current
  column edge, so the first click never collapses the column.
- Choosing an already-checked radio returns to the site's normal column (radios cannot be
  unchecked otherwise). This is the one hidden gesture; flagged in the PR for review.
- The choice is kept in localStorage under forum-<namespace>-reading-margins as "L R".
  Phones (<640px) hide the ruler and ignore a saved column.

Reuse:
- Flag registration, EXPERIENCE group (appended), and the template gate follow
  FORUM_THREAD_DENSITY_TOGGLE_ENABLED (TemplateRenderer page list + layout flag).
- Storage key comes from BrowserRuntimeProfile beside threadDensityStorageKey.
- Pre-paint restore is a block in layout.php's head script, like thread density and
  table zoom; the partial checks its radios with an inline script, like visitor_intro.php.
- New page stylesheet reading-margins.css and script reading_margins.js: no existing
  component positions controls by viewport coordinate.

Verification (2026-11-12):
- ./v3 test --smoke FeatureFlagsBehaviorTest SiteProfileRegistryTest
  LocalAppSmokeTest::testReadingMarginsAreHiddenByDefaultAndShownOnThreadsAndListingsWhenFlagEnabled
  FeatureFlagEvaluatorTest. Full suite not run.
- FeatureFlagsBehaviorTest::testRenderedOrderIgnoresRegistryInputOrder fails on main
  before this change too (tests/Support/feature_flags_toolbar_fixture.php does not pass
  $indent to tools_nav_card.php); not addressed here.
- node tests/browser/reading_margins.mjs [screenshot-dir]: all 16 permitted ZenMemes
  themes at 1280px. Checks that each checked radio is centred within 2px of the column
  edge it sets, the page gains no sideways scroll, a saved choice is restored and the
  column is final on first paint, an off-centre column works, re-choosing resets, and a
  390px phone shows no ruler and keeps the full-width column. Screenshots reviewed for
  every theme.

Limitations:
- Only the ZenMemes profile was rendered; other site profiles share the same shell rules.
- Real devices and Safari were not checked.

Follow-up (2026-11-14): one uniform row
Request: make the radio buttons go uniformly across the top and drop the separate left
and right groups.
- One row of seven stops at 5/20/35/50/65/80/95vw, each with its own name so two can be
  checked. The two checked radios are the column edges; the further-left one is the left
  edge.
- Choosing an unchecked radio moves the nearer edge to it (a stop exactly halfway moves
  the left edge). With nothing chosen, the radio becomes the edge on its side of the
  column's centre, and the other edge goes to the stop nearest the column's other side.
  Choosing a checked radio still resets to the normal column.
- Same storage key and "L R" format. layout.php only accepts two stops from the new list
  in increasing order, so choices saved by the old left/right ruler are ignored.
- Adjacent stops make a 15vw column (about 96px at the 640px breakpoint). Allowed on
  purpose; it is the reader's choice and one more click undoes it.
Verification: ./v3 test --smoke
LocalAppSmokeTest::testReadingMarginsAreHiddenByDefaultAndShownOnThreadsAndListingsWhenFlagEnabled
(452 passed); full suite not run. node tests/browser/reading_margins.mjs across all 17
permitted themes at 1280px plus a 390px phone: one row, equal gaps, nearer-edge moves,
reload restores before paint, reset, old saved values ignored. Screenshots reviewed for
every theme.

Follow-up (2026-11-14): listing pages
Request: add the ruler to listing pages too.
- READING_MARGINS_PAGE_TEMPLATES now covers board.php (/), board_list.php (/threads/),
  board_search.php (/search), tag.php (/tags/<tag>/) and recent.php (/recent/) besides
  thread.php. The saved choice is shared, so one setting applies to threads and lists.
- Not included: gallery.php (its justified rows are laid out for the normal width), tag
  index, profiles, tools and other pages.
Verification: ./v3 test --smoke
LocalAppSmokeTest::testReadingMarginsAreHiddenByDefaultAndShownOnThreadsAndListingsWhenFlagEnabled
FeatureFlagEvaluatorTest (452 passed); full suite not run. node tests/browser/reading_margins.mjs
now also loads all five listing paths in all 17 themes with a saved "20 95": radios checked,
column between them, final on first paint, no sideways scroll. Board screenshots reviewed
for every theme, with and without a saved choice.
