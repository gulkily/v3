(function () {
  // One-time navigation hints: created_post_id for the thread request after a post, __v for
  // the server cache bypass and the version checker, which loads first. They stay in the
  // address bar on the visit that brought them, so it doesn't flash a shorter URL, and are
  // dropped once the person reloads.
  if (!window.history || typeof window.history.replaceState !== 'function'
      || document.documentElement.hasAttribute('data-version-redirect') || !isReload()) {
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

  function isReload() {
    var performance = window.performance;
    if (performance && typeof performance.getEntriesByType === 'function') {
      var entry = performance.getEntriesByType('navigation')[0];
      if (entry) return entry.type === 'reload';
    }
    return Boolean(performance && performance.navigation && performance.navigation.type === 1);
  }
}());
