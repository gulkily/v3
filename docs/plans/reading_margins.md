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
  LocalAppSmokeTest::testReadingMarginsAreHiddenByDefaultAndShownOnThreadsWhenFlagEnabled
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
