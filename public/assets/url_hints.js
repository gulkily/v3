(function () {
  // One-time navigation hints, already used by the time this runs: created_post_id by the
  // thread request after a post, __v by the server cache bypass and the version checker,
  // which loads first. Drop them so the address bar, shared links and reloads stay clean.
  if (!window.history || typeof window.history.replaceState !== 'function'
      || document.documentElement.hasAttribute('data-version-redirect')) {
    return;
  }

  var url = new URL(window.location.href);
  var changed = false;
  if (url.searchParams.has('__v')) {
    url.searchParams.delete('__v');
    changed = true;
  }
  if (/^\/threads\/[^/]+\/?$/.test(url.pathname) && url.searchParams.has('created_post_id')) {
    url.searchParams.delete('created_post_id');
    changed = true;
  }
  if (changed) {
    window.history.replaceState(window.history.state, '', url.toString());
  }
}());
