# Site Profile and Instance State Decoupling: Step 1 Solution Assessment

## Problem statement

Selecting the Chouse site profile currently switches local repositories, approval state, and read-model databases even though presentation choices should not determine an instance's canonical data.

## Option A — Share all default state

### Pros

- Makes site profile selection purely presentational.
- Gives every local theme the same identities, approvals, posts, database, and generated artifacts.
- Requires the fewest configuration concepts.

### Cons

- Theme-dependent generated HTML may be stale after changing profiles unless it is rebuilt.
- Developers wanting isolated local instances must provide explicit paths.

## Option B — Share data, separate presentation cache

### Pros

- Gives one canonical repository and one read-model database per instance.
- Keeps identities, approvals, and content stable when presentation changes.
- Prevents generated HTML for one site profile from being reused under another profile.
- Preserves explicit paths for genuinely separate instances.

### Cons

- Retains separate derived static-cache locations.
- Requires clear documentation that static caches are disposable presentation artifacts, not instance data.

## Option C — Require explicit instance paths

### Pros

- Makes instance boundaries fully explicit and difficult to confuse with themes.
- Avoids implicit path selection in production-like environments.

### Cons

- Adds setup friction for ordinary local development.
- Requires more environment configuration and creates more opportunities for incomplete startup commands.

## Recommendation

Choose **Option B**: use one canonical repository and read-model database per instance regardless of site profile, while allowing profile-specific static caches. Treat the existing default repository as authoritative, preserve the small Chouse sandbox as a recoverable backup, and do not merge its duplicate identity/bootstrap records.
