(function () {
  // created_post_id is a one-time hint for the request that follows a post; the server has
  // already used it. Drop it so the address bar, shared links and reloads stay clean.
  if (!window.history || typeof window.history.replaceState !== 'function') {
    return;
  }

  var url = new URL(window.location.href);
  if (!/^\/threads\/[^/]+\/?$/.test(url.pathname) || !url.searchParams.has('created_post_id')) {
    return;
  }

  url.searchParams.delete('created_post_id');
  window.history.replaceState(window.history.state, '', url.toString());
}());
