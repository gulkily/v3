# Theme script

Purpose: Let a theme do more than restyle the page: add controls a reader can click, run timers, and remove all of it when the theme changes.
Scenario: A reader picks Engagify. `theme_toggle.js` loads `/assets/theme-engagify.js` once; the script registers a start and a stop with `window.forumThemeScripts`, and start adds the nags and the Exit control. When the reader picks another theme, without a reload, stop removes every element and timer the script added; picking Engagify again starts it again from the already loaded script. Themes without a script load nothing.
Sources: [theme registry](../../src/ForumRewrite/View/ThemeRegistry.php) (`script` on an entry, `scriptPaths()`), [registry and loader](../../public/assets/theme_toggle.js) (`forumThemeScripts`, `forumExitTheme`), [Engagify script](../../public/assets/theme-engagify.js), [VM test](../../tests/browser/theme_scripts.cjs), [theme guide](../runbooks/theme_development_guide.md#theme-scripts).

A failing start or stop is logged and does not block switching themes. `forumExitTheme()` returns the reader to the theme they had before the current one, or leaves a [page theme](page_theme.md).
