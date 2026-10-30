# Profile delivery

Profiles are prebuilt by default on every site. On a PHP-served site, turn off **Static profile pages** in Tools → Feature flags to skip profile HTML during builds. The equivalent environment override is `FORUM_STATIC_PROFILE_PAGES_ENABLED=false`; use the same setting for the builder and PHP frontend. Canonical site settings persist across commands; a command-only environment override does not configure the web process.

Run your usual full `./v3 build-static` command with the same repository, database, destination and other arguments after switching. Profile links continue to work through PHP, and profile, identity, approval and history data remain available. This setting is independent of **Static detail pages**, which controls threads and posts.

The PHP frontend bypasses old profile artifacts immediately when disabled. A shared refresh drops copied profile artifacts but does not regenerate them when enabling; a full build restores them. Direct single-route refreshes also skip disabled profiles. Keep this control enabled for static-only hosting, which cannot supply the runtime route. Direct requests to old physical `.html` files are outside PHP routing; retire old public-root exports when changing a static-only deployment to PHP.

A failed candidate leaves the current release active. Correct the reported error and rerun the same full build. To return to prebuilt profiles, enable the control (or remove the false environment override), then complete a full build. Existing profile access rules still apply.
